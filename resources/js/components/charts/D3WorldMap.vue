<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import * as d3 from 'd3';
import { feature } from 'topojson-client';
import type { Topology, GeometryCollection } from 'topojson-specification';
import { router } from '@inertiajs/vue3';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

// @ts-expect-error — world-atlas ships JSON without TS declarations
import worldData from 'world-atlas/countries-110m.json';

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
    top: 5,
    right: 5,
    bottom: 5,
    left: 5,
});

/** Grid-based clustering: group projected points into cells of `radius` px. */
function clusterPoints(
    data: MapDatum[],
    projection: d3.GeoProjection,
    transform: d3.ZoomTransform,
    radius: number,
): Cluster[] {
    const grid = new Map<string, Cluster>();

    for (const d of data) {
        const raw = projection([d.longitude, d.latitude]);
        if (!raw) continue;

        const [px, py] = transform.apply(raw as [number, number]);
        const cellX = Math.floor(px / radius);
        const cellY = Math.floor(py / radius);
        const key = `${cellX},${cellY}`;

        if (!grid.has(key)) {
            grid.set(key, { x: 0, y: 0, points: [], totalScore: 0 });
        }
        const cluster = grid.get(key)!;
        cluster.points.push(d);
        cluster.totalScore += d.hypeScore;
    }

    // Average position for each cluster (in untransformed coords)
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

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const landColor = getColor('--muted');
    const borderColor = getColor('--border');
    const dotColor = getColor('--chart-1');
    const textColor = getColor('--foreground');

    const topology = worldData as unknown as Topology<{ countries: GeometryCollection; land: GeometryCollection }>;
    const countries = feature(topology, topology.objects.countries);

    const projection = d3
        .geoNaturalEarth1()
        .fitSize([width.value - 10, height.value - 10], countries)
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

    // Cluster layer (redrawn on zoom)
    const clusterLayer = g.append('g').attr('class', 'clusters');

    const maxScore = d3.max(props.data, (d) => d.hypeScore) ?? 1;
    const radiusScale = d3.scaleSqrt().domain([0, maxScore]).range([3, 12]);
    const clusterRadius = 40; // px grid cell size

    function renderClusters(transform: d3.ZoomTransform) {
        const clusters = clusterPoints(props.data, projection, transform, clusterRadius);

        clusterLayer.selectAll('*').remove();

        const groups = clusterLayer
            .selectAll('.cluster')
            .data(clusters)
            .enter()
            .append('g')
            .attr('class', 'cluster')
            .attr('transform', (c) => `translate(${c.x},${c.y})`)
            .style('cursor', 'pointer');

        // Single-point clusters: individual dot
        groups
            .filter((c) => c.points.length === 1)
            .append('circle')
            .attr('r', (c) => radiusScale(c.points[0].hypeScore))
            .attr('fill', dotColor)
            .attr('fill-opacity', 0.7)
            .attr('stroke', dotColor)
            .attr('stroke-width', 1 / transform.k)
            .attr('stroke-opacity', 0.9);

        // Multi-point clusters: larger circle with count
        const multi = groups.filter((c) => c.points.length > 1);

        multi
            .append('circle')
            .attr('r', (c) => Math.min(24, 10 + Math.sqrt(c.points.length) * 4))
            .attr('fill', dotColor)
            .attr('fill-opacity', 0.6)
            .attr('stroke', dotColor)
            .attr('stroke-width', 1.5 / transform.k)
            .attr('stroke-opacity', 0.9);

        multi
            .append('text')
            .attr('text-anchor', 'middle')
            .attr('dy', '0.35em')
            .attr('fill', textColor)
            .attr('font-size', `${Math.max(9, 11 / transform.k)}px`)
            .attr('font-weight', '600')
            .attr('pointer-events', 'none')
            .text((c) => c.points.length);

        // Interactions
        groups
            .on('mouseenter', function (event: MouseEvent, c) {
                d3.select(this).select('circle').transition().duration(150).attr('fill-opacity', 1);
                groups
                    .filter((other) => other !== c)
                    .select('circle')
                    .transition()
                    .duration(150)
                    .attr('fill-opacity', 0.2);

                const label =
                    c.points.length === 1
                        ? c.points[0].domain
                        : `${c.points.length} sites`;
                const detail =
                    c.points.length === 1
                        ? `Score ${c.points[0].hypeScore}`
                        : c.points
                              .slice(0, 5)
                              .map((p) => p.domain)
                              .join(', ') + (c.points.length > 5 ? ` +${c.points.length - 5} more` : '');

                tooltip.value = {
                    visible: true,
                    x: event.clientX + 12,
                    y: event.clientY - 10,
                    label,
                    detail,
                };
            })
            .on('mousemove', function (event: MouseEvent) {
                tooltip.value.x = event.clientX + 12;
                tooltip.value.y = event.clientY - 10;
            })
            .on('mouseleave', function () {
                groups.select('circle').transition().duration(200).attr('fill-opacity', (c: Cluster) => (c.points.length === 1 ? 0.7 : 0.6));
                tooltip.value.visible = false;
            })
            .on('click', function (_, c) {
                if (c.points.length === 1) {
                    router.visit(`/sites/${c.points[0].slug}`);
                }
            });
    }

    // Initial render
    renderClusters(d3.zoomIdentity);

    // Zoom & pan
    const zoom = d3
        .zoom<SVGSVGElement, unknown>()
        .scaleExtent([1, 8])
        .on('zoom', (event) => {
            g.attr('transform', event.transform);
            g.select('.countries').selectAll('path').attr('stroke-width', 0.5 / event.transform.k);
            renderClusters(event.transform);
        });

    svg.call(zoom);
}

onResize(draw);
onMounted(draw);
watch(() => props.data, draw, { deep: true });
</script>

<template>
    <div ref="containerRef" class="h-full w-full">
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
