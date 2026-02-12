<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import * as d3 from 'd3';
import { ref, onMounted, watch } from 'vue';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface ScatterDatum {
    label: string;
    x: number;
    y: number;
    slug?: string;
}

const props = defineProps<{
    data: ScatterDatum[];
    xLabel?: string;
    yLabel?: string;
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', xVal: 0, yVal: 0 });

const { innerWidth, innerHeight, margin, createSvg, getColor, onResize, wrapUpdate } = useD3Chart(containerRef, {
    top: 10,
    right: 20,
    bottom: 40,
    left: 50,
});

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const dotColor = getColor('--chart-1');
    const textColor = getColor('--muted-foreground');

    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);

    const xMax = d3.max(props.data, (d) => d.x) ?? 1;
    const yMax = d3.max(props.data, (d) => d.y) ?? 1;

    const x = d3
        .scaleLinear()
        .domain([0, xMax * 1.1])
        .nice()
        .range([0, innerWidth.value]);

    const y = d3
        .scaleLinear()
        .domain([0, yMax * 1.1])
        .nice()
        .range([innerHeight.value, 0]);

    // X axis
    g.append('g')
        .attr('transform', `translate(0,${innerHeight.value})`)
        .call(d3.axisBottom(x).ticks(6).tickSize(-innerHeight.value).tickPadding(8))
        .call((g) => g.select('.domain').remove())
        .call((g) => g.selectAll('.tick line').attr('stroke', textColor).attr('stroke-opacity', 0.15))
        .selectAll('text')
        .style('fill', textColor)
        .style('font-size', '11px');

    // X label
    if (props.xLabel) {
        g.append('text')
            .attr('x', innerWidth.value / 2)
            .attr('y', innerHeight.value + 35)
            .attr('text-anchor', 'middle')
            .style('fill', textColor)
            .style('font-size', '12px')
            .text(props.xLabel);
    }

    // Y axis
    g.append('g')
        .call(d3.axisLeft(y).ticks(6).tickSize(-innerWidth.value).tickPadding(8))
        .call((g) => g.select('.domain').remove())
        .call((g) => g.selectAll('.tick line').attr('stroke', textColor).attr('stroke-opacity', 0.15))
        .selectAll('text')
        .style('fill', textColor)
        .style('font-size', '11px');

    // Y label
    if (props.yLabel) {
        g.append('text')
            .attr('transform', 'rotate(-90)')
            .attr('x', -innerHeight.value / 2)
            .attr('y', -40)
            .attr('text-anchor', 'middle')
            .style('fill', textColor)
            .style('font-size', '12px')
            .text(props.yLabel);
    }

    // Points
    const dots = g
        .selectAll('.dot')
        .data(props.data)
        .enter()
        .append('circle')
        .attr('class', 'dot')
        .attr('cx', (d) => x(d.x))
        .attr('cy', (d) => y(d.y))
        .attr('fill', dotColor)
        .attr('fill-opacity', 0.6)
        .attr('stroke', dotColor)
        .attr('stroke-width', 1)
        .style('cursor', 'pointer')
        .attr('r', 0);

    // Animate points
    dots.transition()
        .duration(400)
        .delay(() => Math.random() * 300)
        .ease(d3.easeBackOut)
        .attr('r', 5);

    // Interaction
    dots.on('mouseenter', function (event: MouseEvent, d) {
        d3.select(this).transition().duration(150).attr('r', 8).attr('fill-opacity', 1);
        dots.filter((other) => other !== d).transition().duration(150).attr('fill-opacity', 0.2);
        tooltip.value = {
            visible: true,
            x: event.clientX + 12,
            y: event.clientY - 10,
            label: d.label,
            xVal: d.x,
            yVal: d.y,
        };
    })
        .on('mousemove', function (event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function () {
            dots.transition().duration(200).attr('r', 5).attr('fill-opacity', 0.6);
            tooltip.value.visible = false;
        })
        .on('click', function (_, d) {
            if (d.slug) {
                router.visit(`/sites/${d.slug}`);
            }
        });
}

onResize(draw);
onMounted(draw);
watch(() => props.data, wrapUpdate(draw), { deep: true });
</script>

<template>
    <div ref="containerRef" class="h-full w-full">
        <Teleport to="body">
            <ChartTooltip :visible="tooltip.visible" :x="tooltip.x" :y="tooltip.y">
                <div class="flex flex-col gap-0.5">
                    <span class="font-medium">{{ tooltip.label }}</span>
                    <span class="tabular-nums text-muted-foreground">{{ tooltip.xVal }} mentions · score {{ tooltip.yVal }}</span>
                </div>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
