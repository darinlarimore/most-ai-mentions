<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import * as d3 from 'd3';
import { hexbin as d3Hexbin } from 'd3-hexbin';
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

type ProjectedPoint = [number, number] & { datum: MapDatum };

const props = withDefaults(
    defineProps<{
        data: MapDatum[];
        mode?: 'hexbin' | 'circles';
    }>(),
    { mode: 'hexbin' },
);

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', sites: [] as MapDatum[] });

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
let overlayLayerSelection: d3.Selection<SVGGElement, unknown, null, undefined> | null = null;
let pingLayerSelection: d3.Selection<SVGGElement, unknown, null, undefined> | null = null;
let currentTransform = d3.zoomIdentity;
let liveData: MapDatum[] = [];
let renderOverlayRef: ((transform: d3.ZoomTransform) => void) | null = null;
let drawGeneration = 0;

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

/** Project data points to SVG coordinates for hexbin layout. */
function projectPoints(data: MapDatum[], projection: d3.GeoProjection): ProjectedPoint[] {
    const projected: ProjectedPoint[] = [];
    for (const d of data) {
        const raw = projection([d.longitude, d.latitude]);
        if (!raw) continue;
        const p = [raw[0], raw[1]] as ProjectedPoint;
        p.datum = d;
        projected.push(p);
    }
    return projected;
}

async function draw() {
    const generation = ++drawGeneration;
    if (!containerRef.value) return;

    const svg = createSvg();
    if (!svg) return;

    const landColor = getColor('--muted');
    const borderColor = getColor('--border');
    const dotColor = getColor('--chart-1');
    const textColor = getColor('--foreground');
    const mutedTextColor = getColor('--muted-foreground');

    const { world, us } = await loadMapData();
    if (!world || !us) return;

    // Another draw() started while we were loading map data — abort this one
    if (generation !== drawGeneration) return;

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

    // Overlay layer (hexbins or clusters rendered here)
    const overlayLayer = g.append('g').attr('class', 'overlay');
    overlayLayerSelection = overlayLayer;

    // Ping animation layer (above overlay so pings aren't wiped by re-rendering)
    const pingLayer = g.append('g').attr('class', 'pings');
    pingLayerSelection = pingLayer;
    storedProjection = projection;
    liveData = [...props.data];

    const maxScore = d3.max(props.data, (d) => d.hypeScore) ?? 1;
    const radiusScale = d3.scaleSqrt().domain([0, maxScore]).range([3, 12]);
    const clusterRadius = 28;
    const hexBaseRadius = 18;

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
            countryLabels.attr('opacity', (d: any) => (k >= countryMinZoom(d.properties.name) ? 0.8 : 0));
            stateLabels.attr('opacity', k >= 4 ? 0.7 : 0);

            renderOverlay(event.transform);
        });

    // ── Shared tooltip helpers ──────────────────────────────────────────

    function showTooltip(event: MouseEvent, points: MapDatum[]) {
        const label = points.length === 1 ? points[0].domain : `${points.length} servers`;
        tooltip.value = { visible: true, x: event.clientX + 12, y: event.clientY - 10, label, sites: points };
    }

    function moveTooltip(event: MouseEvent) {
        tooltip.value.x = event.clientX + 12;
        tooltip.value.y = event.clientY - 10;
    }

    function hideTooltip() {
        tooltip.value.visible = false;
    }

    // ── Hexbin mode ─────────────────────────────────────────────────────

    function renderHexbins(transform: d3.ZoomTransform) {
        const k = transform.k;
        overlayLayer.selectAll('*').remove();

        const projected = projectPoints(liveData, projection);
        if (projected.length === 0) return;

        // Grid cell radius stays constant in screen-space
        const hexRadius = hexBaseRadius / k;

        const hexLayout = d3Hexbin<ProjectedPoint>()
            .x((d) => d[0])
            .y((d) => d[1])
            .radius(hexRadius);

        const bins = hexLayout(projected);
        if (bins.length === 0) return;

        const maxCount = d3.max(bins, (b) => b.length) ?? 1;

        // Sqrt scale: hex area ∝ count (perceptually linear)
        // Min 40% of cell radius so single-server hexes are still visible
        const sizeScale = d3.scaleSqrt().domain([1, maxCount]).range([hexRadius * 0.4, hexRadius]).clamp(true);

        // Color scale: light → saturated version of chart-1
        const colorScale = d3
            .scaleLinear<string>()
            .domain([1, maxCount])
            .range([d3.color(dotColor)!.copy({ opacity: 0.3 }).formatRgb(), dotColor])
            .clamp(true);

        const hexGroups = overlayLayer
            .selectAll('.hex')
            .data(bins.filter((b) => b.length > 0))
            .enter()
            .append('g')
            .attr('class', 'hex')
            .attr('transform', (b) => `translate(${b.x},${b.y})`)
            .style('cursor', 'pointer');

        // Each hex sized proportionally — never exceeds grid cell, so no overlap
        hexGroups
            .append('path')
            .attr('d', (b) => hexLayout.hexagon(sizeScale(b.length))!)
            .attr('fill', (b) => colorScale(b.length))
            .attr('fill-opacity', 0.85)
            .attr('stroke', borderColor)
            .attr('stroke-width', 0.5 / k)
            .attr('stroke-opacity', 0.6);

        // Count labels — only show when hex is large enough to fit text
        hexGroups
            .filter((b) => sizeScale(b.length) > hexRadius * 0.35)
            .append('text')
            .attr('text-anchor', 'middle')
            .attr('dy', '0.35em')
            .attr('fill', textColor)
            .attr('font-size', (b) => `${Math.max(4, sizeScale(b.length) * 0.7)}px`)
            .attr('font-weight', '600')
            .attr('pointer-events', 'none')
            .text((b) => b.length);

        // Interactions
        hexGroups
            .on('mouseenter', function (event: MouseEvent, b) {
                d3.select(this).select('path').transition().duration(150).attr('fill-opacity', 1);
                hexGroups
                    .filter(function () {
                        return this !== event.currentTarget;
                    })
                    .select('path')
                    .transition()
                    .duration(150)
                    .attr('fill-opacity', 0.2);
                const points = b.map((p) => p.datum);
                showTooltip(event, points);
            })
            .on('mousemove', moveTooltip)
            .on('mouseleave', function () {
                hexGroups.select('path').transition().duration(200).attr('fill-opacity', 0.85);
                hideTooltip();
            })
            .on('click', function (event, b) {
                if (b.length === 1) {
                    router.visit(`/sites/${b[0].datum.slug}`);
                } else {
                    event.stopPropagation();
                    const nextK = Math.min(16, k * 2.5);
                    const tx = width.value / 2 - b.x * nextK;
                    const ty = height.value / 2 - b.y * nextK;
                    svg.transition()
                        .duration(500)
                        .call(zoom.transform, d3.zoomIdentity.translate(tx, ty).scale(nextK));
                }
            });
    }

    // ── Circles mode ────────────────────────────────────────────────────

    function renderCircles(transform: d3.ZoomTransform) {
        const k = transform.k;
        const clusters = clusterPoints(liveData, projection, transform, clusterRadius);

        overlayLayer.selectAll('*').remove();

        // For high zoom, spread co-located points into a ring
        const spreadThreshold = 6;
        const expandedClusters: { x: number; y: number; point: MapDatum; isCluster: false }[] = [];
        const keptClusters: Cluster[] = [];

        for (const cluster of clusters) {
            if (cluster.points.length > 1 && k >= spreadThreshold) {
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

        const groups = overlayLayer
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

        const ringDots = overlayLayer
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

        function dimOthers(active: any) {
            groups
                .filter(function () {
                    return this !== active;
                })
                .select('circle')
                .transition()
                .duration(150)
                .attr('fill-opacity', 0.15);
            ringDots
                .filter(function () {
                    return this !== active;
                })
                .transition()
                .duration(150)
                .attr('fill-opacity', 0.15);
        }

        function restoreAll() {
            groups
                .select('circle')
                .transition()
                .duration(200)
                .attr('fill-opacity', (c: Cluster) => (c.points.length === 1 ? 0.7 : 0.6));
            ringDots
                .transition()
                .duration(200)
                .attr('fill-opacity', 0.7)
                .attr('r', (d) => radiusScale(d.point.hypeScore) / k);
            hideTooltip();
        }

        groups
            .on('mouseenter', function (event: MouseEvent, c) {
                d3.select(this).select('circle').transition().duration(150).attr('fill-opacity', 1);
                dimOthers(this);
                showTooltip(event, c.points);
            })
            .on('mousemove', moveTooltip)
            .on('mouseleave', restoreAll)
            .on('click', function (event, c) {
                if (c.points.length === 1) {
                    router.visit(`/sites/${c.points[0].slug}`);
                } else {
                    event.stopPropagation();
                    const nextK = Math.min(16, currentTransform.k * 2.5);
                    const tx = width.value / 2 - c.x * nextK;
                    const ty = height.value / 2 - c.y * nextK;
                    svg.transition()
                        .duration(500)
                        .call(zoom.transform, d3.zoomIdentity.translate(tx, ty).scale(nextK));
                }
            });

        ringDots
            .on('mouseenter', function (event: MouseEvent, d) {
                d3.select(this)
                    .transition()
                    .duration(150)
                    .attr('fill-opacity', 1)
                    .attr('r', radiusScale(d.point.hypeScore) / k + 2 / k);
                dimOthers(this);
                showTooltip(event, [d.point]);
            })
            .on('mousemove', moveTooltip)
            .on('mouseleave', restoreAll)
            .on('click', function (_, d) {
                router.visit(`/sites/${d.point.slug}`);
            });
    }

    // ── Overlay dispatcher ──────────────────────────────────────────────

    function renderOverlay(transform: d3.ZoomTransform) {
        if (props.mode === 'hexbin') {
            renderHexbins(transform);
        } else {
            renderCircles(transform);
        }
    }

    renderOverlayRef = renderOverlay;
    renderOverlay(d3.zoomIdentity);
    svg.call(zoom).on('wheel.zoom', null);
    zoomBehavior = zoom;
    svgSelection = svg;
}

onResize(draw);
onMounted(draw);
watch(() => props.data, draw, { deep: true });
watch(
    () => props.mode,
    () => {
        if (renderOverlayRef) renderOverlayRef(currentTransform);
    },
);

function addPoint(point: MapDatum) {
    if (!storedProjection || !pingLayerSelection) return;

    const projected = storedProjection([point.longitude, point.latitude]);
    if (!projected) return;

    const [px, py] = projected;
    const dotColor = getColor('--chart-1');
    const k = currentTransform.k;

    // Ping animation group (on separate layer so overlay re-renders don't wipe it)
    const ping = pingLayerSelection.append('g').attr('transform', `translate(${px},${py})`);

    // Expanding rings
    for (let i = 0; i < 3; i++) {
        ping.append('circle')
            .attr('r', 3 / k)
            .attr('fill', 'none')
            .attr('stroke', dotColor)
            .attr('stroke-width', 2.5 / k)
            .attr('stroke-opacity', 1)
            .transition()
            .delay(i * 250)
            .duration(1400)
            .ease(d3.easeCubicOut)
            .attr('r', 50 / k)
            .attr('stroke-opacity', 0)
            .remove();
    }

    // Center dot that scales in and holds
    ping.append('circle')
        .attr('r', 0)
        .attr('fill', dotColor)
        .attr('fill-opacity', 1)
        .attr('stroke', 'white')
        .attr('stroke-width', 1.5 / k)
        .attr('stroke-opacity', 0.9)
        .transition()
        .duration(400)
        .ease(d3.easeBackOut.overshoot(2))
        .attr('r', 8 / k)
        .transition()
        .delay(1200)
        .duration(500)
        .attr('fill-opacity', 0)
        .attr('stroke-opacity', 0)
        .remove();

    // Remove the ping group after all animations complete
    setTimeout(() => ping.remove(), 2500);

    // Add point to live data and re-render overlay
    liveData.push(point);
    if (renderOverlayRef) {
        renderOverlayRef(currentTransform);
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
                <div class="flex flex-col gap-1">
                    <span class="font-semibold">{{ tooltip.label }}</span>
                    <div v-for="site in tooltip.sites.slice(0, 8)" :key="site.slug" class="flex items-center justify-between gap-3 text-xs">
                        <span class="text-muted-foreground">{{ site.domain }}</span>
                        <span class="tabular-nums">{{ site.hypeScore }}</span>
                    </div>
                    <span v-if="tooltip.sites.length > 8" class="text-xs text-muted-foreground">+{{ tooltip.sites.length - 8 }} more</span>
                </div>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
