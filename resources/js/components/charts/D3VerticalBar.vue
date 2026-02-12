<script setup lang="ts">
import * as d3 from 'd3';
import { ref, onMounted, watch } from 'vue';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface VerticalBarDatum {
    label: string;
    value: number;
}

const props = defineProps<{
    data: VerticalBarDatum[];
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', value: 0 });

const { innerWidth, innerHeight, margin, createSvg, getChartColors, getColor, onResize, wrapUpdate } = useD3Chart(
    containerRef,
    { top: 10, right: 10, bottom: 30, left: 45 },
);

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const colors = getChartColors(props.data.length);
    const textColor = getColor('--muted-foreground');

    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);

    const x = d3
        .scaleBand<string>()
        .domain(props.data.map((d) => d.label))
        .range([0, innerWidth.value])
        .padding(0.3);

    const y = d3
        .scaleLinear()
        .domain([0, d3.max(props.data, (d) => d.value) ?? 1])
        .nice()
        .range([innerHeight.value, 0]);

    // X axis
    g.append('g')
        .attr('transform', `translate(0,${innerHeight.value})`)
        .call(d3.axisBottom(x).tickSize(0).tickPadding(8))
        .call((g) => g.select('.domain').remove())
        .selectAll('text')
        .style('fill', textColor)
        .style('font-size', '11px');

    // Y axis
    g.append('g')
        .call(d3.axisLeft(y).ticks(5).tickSize(-innerWidth.value).tickPadding(8))
        .call((g) => g.select('.domain').remove())
        .call((g) => g.selectAll('.tick line').attr('stroke', textColor).attr('stroke-opacity', 0.15))
        .selectAll('text')
        .style('fill', textColor)
        .style('font-size', '11px');

    // Bars
    const bars = g
        .selectAll('.bar')
        .data(props.data)
        .enter()
        .append('rect')
        .attr('class', 'bar')
        .attr('x', (d) => x(d.label) ?? 0)
        .attr('width', x.bandwidth())
        .attr('rx', 4)
        .attr('fill', (_, i) => colors[i % colors.length])
        .attr('y', innerHeight.value)
        .attr('height', 0);

    // Animate from bottom
    bars.transition()
        .duration(500)
        .delay((_, i) => i * 60)
        .ease(d3.easeBackOut.overshoot(0.5))
        .attr('y', (d) => y(d.value))
        .attr('height', (d) => innerHeight.value - y(d.value));

    // Interaction
    bars.on('mouseenter', function (event: MouseEvent, d) {
        d3.select(this).transition().duration(150).attr('fill-opacity', 1);
        bars.filter((other) => other !== d).transition().duration(150).attr('fill-opacity', 0.35);
        tooltip.value = { visible: true, x: event.clientX + 12, y: event.clientY - 10, label: d.label, value: d.value };
    })
        .on('mousemove', function (event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function () {
            bars.transition().duration(200).attr('fill-opacity', 1);
            tooltip.value.visible = false;
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
                <span class="font-medium">{{ tooltip.label }}</span>
                <span class="ml-2 tabular-nums text-muted-foreground">{{ tooltip.value.toLocaleString() }}</span>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
