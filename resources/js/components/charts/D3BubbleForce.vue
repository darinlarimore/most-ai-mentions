<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import * as d3 from 'd3';
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface BubbleForceDatum {
    label: string;
    x: number;
    y: number;
    slug?: string;
}

const props = defineProps<{
    data: BubbleForceDatum[];
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', mentions: 0, score: 0 });
let simulation: d3.Simulation<any, undefined> | null = null;

const { width, height, createSvg, getColor, getChartColors, onResize } = useD3Chart(containerRef, {
    top: 10,
    right: 10,
    bottom: 10,
    left: 10,
});

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    // Stop previous simulation
    if (simulation) {
        simulation.stop();
        simulation = null;
    }

    const svg = createSvg();
    if (!svg) return;

    const colors = getChartColors(5);
    const textColor = getColor('--muted-foreground');

    const maxScore = d3.max(props.data, (d) => d.y) ?? 1;
    const maxMentions = d3.max(props.data, (d) => d.x) ?? 1;

    // Bubble size based on score
    const radiusScale = d3
        .scaleSqrt()
        .domain([0, maxScore])
        .range([4, Math.min(width.value, height.value) / 12]);

    // Color based on mentions
    const colorScale = d3
        .scaleQuantize<string>()
        .domain([0, maxMentions])
        .range(colors);

    const nodes = props.data.map((d) => ({
        ...d,
        r: radiusScale(d.y),
    }));

    const g = svg.append('g').attr('transform', `translate(${width.value / 2},${height.value / 2})`);

    const bubbles = g
        .selectAll('.bubble')
        .data(nodes)
        .enter()
        .append('circle')
        .attr('class', 'bubble')
        .attr('r', 0)
        .attr('fill', (d) => colorScale(d.x))
        .attr('fill-opacity', 0.75)
        .attr('stroke', (d) => colorScale(d.x))
        .attr('stroke-width', 1)
        .style('cursor', 'pointer');

    // Labels inside large bubbles
    const labels = g
        .selectAll('.bubble-label')
        .data(nodes.filter((d) => d.r > 18))
        .enter()
        .append('text')
        .attr('class', 'bubble-label')
        .attr('text-anchor', 'middle')
        .attr('dy', '0.35em')
        .style('font-size', (d) => `${Math.min(d.r / 3, 11)}px`)
        .style('fill', '#fff')
        .style('pointer-events', 'none')
        .style('opacity', 0)
        .text((d) => d.label);

    simulation = d3
        .forceSimulation(nodes as any)
        .force('charge', d3.forceManyBody().strength(2))
        .force('center', d3.forceCenter(0, 0))
        .force(
            'collision',
            d3.forceCollide<any>().radius((d) => d.r + 1.5),
        )
        .on('tick', () => {
            bubbles.attr('cx', (d: any) => d.x).attr('cy', (d: any) => d.y);
            labels.attr('x', (d: any) => d.x).attr('y', (d: any) => d.y);
        });

    // Animate radius after simulation stabilizes a bit
    bubbles
        .transition()
        .duration(500)
        .delay((_, i) => i * 10)
        .ease(d3.easeBackOut.overshoot(0.4))
        .attr('r', (d) => d.r);

    labels.transition().delay(600).duration(200).style('opacity', 1);

    // Legend for color scale
    const legendG = svg.append('g').attr('transform', `translate(${width.value - 10},${height.value - 10})`);
    legendG
        .append('text')
        .attr('text-anchor', 'end')
        .attr('dy', '-4')
        .style('font-size', '10px')
        .style('fill', textColor)
        .text('mentions \u2192 color, score \u2192 size');

    // Interaction
    bubbles
        .on('mouseenter', function (event: MouseEvent, d: any) {
            d3.select(this).transition().duration(150).attr('r', d.r * 1.15).attr('fill-opacity', 1);
            bubbles.filter((other) => other !== d).transition().duration(150).attr('fill-opacity', 0.25);
            tooltip.value = {
                visible: true,
                x: event.clientX + 12,
                y: event.clientY - 10,
                label: d.label,
                mentions: d.x,
                score: d.y,
            };
        })
        .on('mousemove', function (event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function () {
            bubbles
                .transition()
                .duration(200)
                .attr('r', (d: any) => d.r)
                .attr('fill-opacity', 0.75);
            tooltip.value.visible = false;
        })
        .on('click', function (_, d: any) {
            if (d.slug) {
                router.visit(`/sites/${d.slug}`);
            }
        });
}

onResize(draw);
onMounted(draw);
watch(() => props.data, draw, { deep: true });

onBeforeUnmount(() => {
    if (simulation) {
        simulation.stop();
        simulation = null;
    }
});
</script>

<template>
    <div ref="containerRef" class="h-full w-full">
        <Teleport to="body">
            <ChartTooltip :visible="tooltip.visible" :x="tooltip.x" :y="tooltip.y">
                <div class="flex flex-col gap-0.5">
                    <span class="font-medium">{{ tooltip.label }}</span>
                    <span class="tabular-nums text-muted-foreground"
                        >{{ tooltip.mentions }} mentions &middot; score {{ tooltip.score }}</span
                    >
                </div>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
