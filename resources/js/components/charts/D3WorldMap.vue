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

const props = defineProps<{
    data: MapDatum[];
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, domain: '', hypeScore: 0 });

const { width, height, createSvg, getColor, onResize } = useD3Chart(containerRef, {
    top: 5,
    right: 5,
    bottom: 5,
    left: 5,
});

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const landColor = getColor('--muted');
    const borderColor = getColor('--border');
    const dotColor = getColor('--chart-1');

    const topology = worldData as unknown as Topology<{ countries: GeometryCollection; land: GeometryCollection }>;
    const countries = feature(topology, topology.objects.countries);

    // Fit projection to container
    const projection = d3
        .geoNaturalEarth1()
        .fitSize([width.value - 10, height.value - 10], countries)
        .translate([width.value / 2, height.value / 2]);

    const path = d3.geoPath(projection);

    // Draw countries
    svg.append('g')
        .selectAll('path')
        .data(countries.features)
        .enter()
        .append('path')
        .attr('d', path as any)
        .attr('fill', landColor)
        .attr('stroke', borderColor)
        .attr('stroke-width', 0.5);

    // Scale dot radius by hype score
    const maxScore = d3.max(props.data, (d) => d.hypeScore) ?? 1;
    const radiusScale = d3.scaleSqrt().domain([0, maxScore]).range([3, 12]);

    // Draw site dots
    const dots = svg
        .append('g')
        .selectAll('.site-dot')
        .data(props.data)
        .enter()
        .append('circle')
        .attr('class', 'site-dot')
        .attr('cx', (d) => {
            const coords = projection([d.longitude, d.latitude]);
            return coords ? coords[0] : 0;
        })
        .attr('cy', (d) => {
            const coords = projection([d.longitude, d.latitude]);
            return coords ? coords[1] : 0;
        })
        .attr('fill', dotColor)
        .attr('fill-opacity', 0.7)
        .attr('stroke', dotColor)
        .attr('stroke-width', 1)
        .attr('stroke-opacity', 0.9)
        .style('cursor', 'pointer')
        .attr('r', 0);

    // Animate dots in
    dots.transition()
        .duration(400)
        .delay(() => Math.random() * 400)
        .ease(d3.easeBackOut)
        .attr('r', (d) => radiusScale(d.hypeScore));

    // Interactions
    dots.on('mouseenter', function (event: MouseEvent, d) {
        const targetR = radiusScale(d.hypeScore);
        d3.select(this).transition().duration(150).attr('r', targetR + 3).attr('fill-opacity', 1);
        dots.filter((other) => other !== d).transition().duration(150).attr('fill-opacity', 0.25);
        tooltip.value = {
            visible: true,
            x: event.clientX + 12,
            y: event.clientY - 10,
            domain: d.domain,
            hypeScore: d.hypeScore,
        };
    })
        .on('mousemove', function (event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function () {
            dots.transition()
                .duration(200)
                .attr('r', (d) => radiusScale(d.hypeScore))
                .attr('fill-opacity', 0.7);
            tooltip.value.visible = false;
        })
        .on('click', function (_, d) {
            router.visit(`/sites/${d.slug}`);
        });
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
                    <span class="font-medium">{{ tooltip.domain }}</span>
                    <span class="tabular-nums text-muted-foreground">Score {{ tooltip.hypeScore }}</span>
                </div>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
