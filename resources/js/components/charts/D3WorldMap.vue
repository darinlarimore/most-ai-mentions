<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import * as d3 from 'd3';
import { Minus, Plus, RotateCcw } from 'lucide-vue-next';
import { feature } from 'topojson-client';
import type { Topology, GeometryCollection } from 'topojson-specification';
import { ref, onMounted, watch } from 'vue';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface MapDatum {
    domain: string;
    slug: string;
    latitude: number;
    longitude: number;
    hypeScore: number;
}

interface Cluster {
    x: number;
    y: number;
    points: MapDatum[];
    totalScore: number;
}

const props = defineProps<{
    data: MapDatum[];
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', detail: '' });

const { width, height, createSvg, getColor, onResize } = useD3Chart(containerRef, {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
});

let worldDataCache: any = null;
let usDataCache: any = null;
let zoomBehavior: d3.ZoomBehavior<SVGSVGElement, unknown> | null = null;
let svgSelection: d3.Selection<SVGSVGElement, unknown, null, undefined> | null = null;
let storedProjection: d3.GeoProjection | null = null;
let clusterLayerSelection: d3.Selection<SVGGElement, unknown, null, undefined> | null = null;
let currentTransform = d3.zoomIdentity;
let liveData: MapDatum[] = [];
let renderClustersRef: ((transform: d3.ZoomTransform) => void) | null = null;

function zoomIn() {
    if (svgSelection && zoomBehavior) {
        svgSelection.transition().duration(300).call(zoomBehavior.scaleBy, 2);
    }
}

function zoomOut() {
    if (svgSelection && zoomBehavior) {
        svgSelection.transition().duration(300).call(zoomBehavior.scaleBy, 0.5);
    }
}

function resetZoom() {
    if (svgSelection && zoomBehavior) {
        svgSelection.transition().duration(300).call(zoomBehavior.transform, d3.zoomIdentity);
    }
}

async function loadMapData() {
    // Guard with SSR check so Vite tree-shakes atlas JSON from the SSR bundle
    if (import.meta.env.SSR) {
        return { world: null, us: null };
    }

    const [world, us] = await Promise.all([
        worldDataCache
            ? Promise.resolve(worldDataCache)
            : // @ts-expect-error — world-atlas ships JSON without TS declarations
              import('world-atlas/countries-50m.json').then((m) => {
                  worldDataCache = m.default;
                  return m.default;
              }),
        usDataCache
            ? Promise.resolve(usDataCache)
            : // @ts-expect-error — us-atlas ships JSON without TS declarations
              import('us-atlas/states-10m.json').then((m) => {
                  usDataCache = m.default;
                  return m.default;
              }),
    ]);
    return { world, us };
}

/** Grid-based clustering with co-location ring spread. */
function clusterPoints(
    data: MapDatum[],
    projection: d3.GeoProjection,
    transform: d3.ZoomTransform,
    radius: number,
): Cluster[] {
    const grid = new Map<string, Cluster>();
    const effectiveRadius = radius / transform.k;

    for (const d of data) {
        const raw = projection([d.longitude, d.latitude]);
        if (!raw) continue;

        const cellX = Math.floor(raw[0] / effectiveRadius);
        const cellY = Math.floor(raw[1] / effectiveRadius);
        const key = `${cellX},${cellY}`;

        if (!grid.has(key)) {
            grid.set(key, { x: 0, y: 0, points: [], totalScore: 0 });
        }
        const cluster = grid.get(key)!;
        cluster.points.push(d);
        cluster.totalScore += d.hypeScore;
    }

    for (const cluster of grid.values()) {
        let sx = 0;
        let sy = 0;
        for (const d of cluster.points) {
            const coords = projection([d.longitude, d.latitude])!;
            sx += coords[0];
            sy += coords[1];
        }
        cluster.x = sx / cluster.points.length;
        cluster.y = sy / cluster.points.length;
    }

    return [...grid.values()];
}

async function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const landColor = getColor('--muted');
    const borderColor = getColor('--border');
    const dotColor = getColor('--chart-1');
    const textColor = getColor('--foreground');
    const mutedTextColor = getColor('--muted-foreground');

    const { world, us } = await loadMapData();

    const worldTopology = world as unknown as Topology<{ countries: GeometryCollection }>;
    const countries = feature(worldTopology, worldTopology.objects.countries);

    const usTopology = us as unknown as Topology<{ states: GeometryCollection }>;
    const states = feature(usTopology, usTopology.objects.states);

    const projection = d3
        .geoNaturalEarth1()
        .fitSize([width.value, height.value], countries)
        .translate([width.value / 2, height.value / 2]);

    const path = d3.geoPath(projection);

    // Wrapper group for zoom/pan
    const g = svg.append('g');

    // Draw countries
    g.append('g')
        .attr('class', 'countries')
        .selectAll('path')
        .data(countries.features)
        .enter()
        .append('path')
        .attr('d', path as any)
        .attr('fill', landColor)
        .attr('stroke', borderColor)
        .attr('stroke-width', 0.5);

    // Draw US state borders (on top of US country fill)
    g.append('g')
        .attr('class', 'states')
        .selectAll('path')
        .data(states.features)
        .enter()
        .append('path')
        .attr('d', path as any)
        .attr('fill', 'none')
        .attr('stroke', borderColor)
        .attr('stroke-width', 0.3)
        .attr('stroke-opacity', 0.6);

    // Compute projected area for each country to size labels proportionally
    const countryAreas = new Map<string, number>();
    for (const f of countries.features) {
        const a = path.area(f as any);
        if (f.properties?.name) countryAreas.set(f.properties.name, a);
    }
    const maxArea = d3.max([...countryAreas.values()]) ?? 1;
    // Large countries (>1% of max): show at zoom 2, font 3–8px
    // Medium countries (0.1–1%): show at zoom 3, font 2.5–4px
    // Small countries (<0.1%): show at zoom 5, font 2–3px
    function countryFontSize(name: string): number {
        const ratio = (countryAreas.get(name) ?? 0) / maxArea;
        if (ratio > 0.01) return 3 + Math.sqrt(ratio) * 5;
        if (ratio > 0.001) return 2.5 + ratio * 150;
        return 2;
    }
    function countryMinZoom(name: string): number {
        const ratio = (countryAreas.get(name) ?? 0) / maxArea;
        if (ratio > 0.01) return 2;
        if (ratio > 0.001) return 3;
        return 5;
    }

    // Country labels (hidden initially, shown on zoom based on size)
    const countryLabels = g
        .append('g')
        .attr('class', 'country-labels')
        .selectAll('text')
        .data(countries.features.filter((f: any) => f.properties?.name))
        .enter()
        .append('text')
        .attr('transform', (d: any) => {
            const centroid = path.centroid(d);
            return `translate(${centroid[0]},${centroid[1]})`;
        })
        .attr('text-anchor', 'middle')
        .attr('dy', '0.35em')
        .attr('fill', mutedTextColor)
        .attr('font-size', (d: any) => `${countryFontSize(d.properties.name)}px`)
        .attr('font-weight', '500')
        .attr('pointer-events', 'none')
        .attr('opacity', 0)
        .text((d: any) => d.properties.name);

    // US state labels (shown at higher zoom)
    const stateLabels = g
        .append('g')
        .attr('class', 'state-labels')
        .selectAll('text')
        .data(states.features.filter((f: any) => f.properties?.name))
        .enter()
        .append('text')
        .attr('transform', (d: any) => {
            const centroid = path.centroid(d);
            return `translate(${centroid[0]},${centroid[1]})`;
        })
        .attr('text-anchor', 'middle')
        .attr('dy', '0.35em')
        .attr('fill', mutedTextColor)
        .attr('font-size', '2px')
        .attr('font-weight', '500')
        .attr('pointer-events', 'none')
        .attr('opacity', 0)
        .text((d: any) => d.properties.name);

    // Cluster layer
    const clusterLayer = g.append('g').attr('class', 'clusters');
    clusterLayerSelection = clusterLayer;
    storedProjection = projection;
    liveData = [...props.data];

    const maxScore = d3.max(props.data, (d) => d.hypeScore) ?? 1;
    const radiusScale = d3.scaleSqrt().domain([0, maxScore]).range([3, 12]);
    const clusterRadius = 28;

    currentTransform = d3.zoomIdentity;

    const zoom = d3
        .zoom<SVGSVGElement, unknown>()
        .scaleExtent([1, 16])
        .on('zoom', (event) => {
            currentTransform = event.transform;
            g.attr('transform', event.transform.toString());

            const k = event.transform.k;

            // Scale stroke widths
            g.select('.countries').selectAll('path').attr('stroke-width', 0.5 / k);
            g.select('.states').selectAll('path').attr('stroke-width', 0.3 / k);

            // Show/hide labels based on zoom level and country size
            countryLabels.attr('opacity', (d: any) => k >= countryMinZoom(d.properties.name) ? 0.8 : 0);
            stateLabels.attr('opacity', k >= 4 ? 0.7 : 0);

            renderClusters(event.transform);
        });

    function renderClusters(transform: d3.ZoomTransform) {
        const k = transform.k;
        const clusters = clusterPoints(liveData, projection, transform, clusterRadius);

        clusterLayer.selectAll('*').remove();

        // For high zoom, spread co-located points into a ring
        const spreadThreshold = 6;
        const expandedClusters: { x: number; y: number; point: MapDatum; isCluster: false }[] = [];
        const keptClusters: Cluster[] = [];

        for (const cluster of clusters) {
            if (cluster.points.length > 1 && k >= spreadThreshold) {
                // Spread into ring
                const ringRadius = (8 + cluster.points.length * 2) / k;
                cluster.points.forEach((p, i) => {
                    const angle = (2 * Math.PI * i) / cluster.points.length - Math.PI / 2;
                    expandedClusters.push({
                        x: cluster.x + Math.cos(angle) * ringRadius,
                        y: cluster.y + Math.sin(angle) * ringRadius,
                        point: p,
                        isCluster: false,
                    });
                });
            } else {
                keptClusters.push(cluster);
            }
        }

        // Render kept clusters (single or multi at low zoom)
        const groups = clusterLayer
            .selectAll('.cluster')
            .data(keptClusters)
            .enter()
            .append('g')
            .attr('class', 'cluster')
            .attr('transform', (c) => `translate(${c.x},${c.y})`)
            .style('cursor', 'pointer');

        groups
            .filter((c) => c.points.length === 1)
            .append('circle')
            .attr('r', (c) => radiusScale(c.points[0].hypeScore) / k)
            .attr('fill', dotColor)
            .attr('fill-opacity', 0.7)
            .attr('stroke', dotColor)
            .attr('stroke-width', 1 / k)
            .attr('stroke-opacity', 0.9);

        const multi = groups.filter((c) => c.points.length > 1);
        multi
            .append('circle')
            .attr('r', (c) => Math.min(24, 10 + Math.sqrt(c.points.length) * 4) / k)
            .attr('fill', dotColor)
            .attr('fill-opacity', 0.6)
            .attr('stroke', dotColor)
            .attr('stroke-width', 1.5 / k)
            .attr('stroke-opacity', 0.9);
        multi
            .append('text')
            .attr('text-anchor', 'middle')
            .attr('dy', '0.35em')
            .attr('fill', textColor)
            .attr('font-size', `${Math.max(8, 11 / k)}px`)
            .attr('font-weight', '600')
            .attr('pointer-events', 'none')
            .text((c) => c.points.length);

        // Render expanded (ring-spread) individual dots
        const ringDots = clusterLayer
            .selectAll('.ring-dot')
            .data(expandedClusters)
            .enter()
            .append('circle')
            .attr('class', 'ring-dot')
            .attr('cx', (d) => d.x)
            .attr('cy', (d) => d.y)
            .attr('r', (d) => radiusScale(d.point.hypeScore) / k)
            .attr('fill', dotColor)
            .attr('fill-opacity', 0.7)
            .attr('stroke', dotColor)
            .attr('stroke-width', 1 / k)
            .attr('stroke-opacity', 0.9)
            .style('cursor', 'pointer');

        // Cluster interactions
        groups
            .on('mouseenter', function (event: MouseEvent, c) {
                d3.select(this).select('circle').transition().duration(150).attr('fill-opacity', 1);
                dimOthers(this);
                showTooltip(event, c.points);
            })
            .on('mousemove', moveTooltip)
            .on('mouseleave', function () {
                restoreAll(groups, ringDots);
            })
            .on('click', function (event, c) {
                if (c.points.length === 1) {
                    router.visit(`/sites/${c.points[0].slug}`);
                } else {
                    event.stopPropagation();
                    const nextK = Math.min(16, currentTransform.k * 2.5);
                    const tx = width.value / 2 - c.x * nextK;
                    const ty = height.value / 2 - c.y * nextK;
                    svg.transition().duration(500).call(zoom.transform, d3.zoomIdentity.translate(tx, ty).scale(nextK));
                }
            });

        // Ring dot interactions
        ringDots
            .on('mouseenter', function (event: MouseEvent, d) {
                d3.select(this).transition().duration(150).attr('fill-opacity', 1).attr('r', radiusScale(d.point.hypeScore) / k + 2 / k);
                dimOthers(this);
                showTooltip(event, [d.point]);
            })
            .on('mousemove', moveTooltip)
            .on('mouseleave', function () {
                restoreAll(groups, ringDots);
            })
            .on('click', function (_, d) {
                router.visit(`/sites/${d.point.slug}`);
            });

        function dimOthers(active: any) {
            groups.filter(function () { return this !== active; }).select('circle').transition().duration(150).attr('fill-opacity', 0.15);
            ringDots.filter(function () { return this !== active; }).transition().duration(150).attr('fill-opacity', 0.15);
        }

        function restoreAll(
            grps: d3.Selection<SVGGElement, Cluster, SVGGElement, unknown>,
            dots: d3.Selection<SVGCircleElement, { x: number; y: number; point: MapDatum; isCluster: false }, SVGGElement, unknown>,
        ) {
            grps.select('circle').transition().duration(200).attr('fill-opacity', (c: Cluster) => (c.points.length === 1 ? 0.7 : 0.6));
            dots.transition().duration(200).attr('fill-opacity', 0.7).attr('r', (d) => radiusScale(d.point.hypeScore) / k);
            tooltip.value.visible = false;
        }

        function showTooltip(event: MouseEvent, points: MapDatum[]) {
            const label = points.length === 1 ? points[0].domain : `${points.length} sites`;
            const detail =
                points.length === 1
                    ? `Score ${points[0].hypeScore}`
                    : points
                          .slice(0, 5)
                          .map((p) => p.domain)
                          .join(', ') + (points.length > 5 ? ` +${points.length - 5} more` : '');
            tooltip.value = { visible: true, x: event.clientX + 12, y: event.clientY - 10, label, detail };
        }

        function moveTooltip(event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        }
    }

    renderClustersRef = renderClusters;
    renderClusters(d3.zoomIdentity);
    svg.call(zoom).on('wheel.zoom', null);
    zoomBehavior = zoom;
    svgSelection = svg;
}

onResize(draw);
onMounted(draw);
watch(() => props.data, draw, { deep: true });

function addPoint(point: MapDatum) {
    if (!storedProjection || !clusterLayerSelection) return;

    const projected = storedProjection([point.longitude, point.latitude]);
    if (!projected) return;

    const [px, py] = projected;
    const dotColor = getColor('--chart-1');
    const k = currentTransform.k;

    // Ping animation group
    const ping = clusterLayerSelection.append('g').attr('transform', `translate(${px},${py})`);

    // Expanding rings
    for (let i = 0; i < 3; i++) {
        ping.append('circle')
            .attr('r', 2 / k)
            .attr('fill', 'none')
            .attr('stroke', dotColor)
            .attr('stroke-width', 1.5 / k)
            .attr('stroke-opacity', 0.8)
            .transition()
            .delay(i * 200)
            .duration(1000)
            .ease(d3.easeCubicOut)
            .attr('r', 24 / k)
            .attr('stroke-opacity', 0)
            .remove();
    }

    // Center dot that scales in
    ping.append('circle')
        .attr('r', 0)
        .attr('fill', dotColor)
        .attr('fill-opacity', 0.9)
        .attr('stroke', dotColor)
        .attr('stroke-width', 1 / k)
        .transition()
        .duration(300)
        .ease(d3.easeBackOut)
        .attr('r', 5 / k)
        .transition()
        .delay(700)
        .duration(300)
        .attr('fill-opacity', 0)
        .attr('stroke-opacity', 0)
        .remove();

    // Remove the ping group after all animations complete
    setTimeout(() => ping.remove(), 1500);

    // Add point to live data and re-render clusters
    liveData.push(point);
    if (renderClustersRef) {
        renderClustersRef(currentTransform);
    }
}

defineExpose({ addPoint });
</script>

<template>
    <div class="relative h-full w-full">
        <div ref="containerRef" class="h-full w-full" />
        <div class="absolute bottom-3 right-3 flex flex-col gap-1">
            <button
                class="rounded-md bg-muted/80 p-1.5 text-muted-foreground backdrop-blur-sm transition-colors hover:bg-muted hover:text-foreground"
                @click="zoomIn"
            >
                <Plus class="size-4" />
            </button>
            <button
                class="rounded-md bg-muted/80 p-1.5 text-muted-foreground backdrop-blur-sm transition-colors hover:bg-muted hover:text-foreground"
                @click="zoomOut"
            >
                <Minus class="size-4" />
            </button>
            <button
                class="rounded-md bg-muted/80 p-1.5 text-muted-foreground backdrop-blur-sm transition-colors hover:bg-muted hover:text-foreground"
                @click="resetZoom"
            >
                <RotateCcw class="size-3.5" />
            </button>
        </div>
        <Teleport to="body">
            <ChartTooltip :visible="tooltip.visible" :x="tooltip.x" :y="tooltip.y">
                <div class="flex flex-col gap-0.5">
                    <span class="font-medium">{{ tooltip.label }}</span>
                    <span class="text-xs text-muted-foreground">{{ tooltip.detail }}</span>
                </div>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
