<script setup lang="ts">
import * as d3 from 'd3';
import { ref, onMounted, watch } from 'vue';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface StackedBarDatum {
    date: string;
    [category: string]: string | number;
}

const props = defineProps<{
    data: StackedBarDatum[];
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, date: '', entries: [] as { key: string; value: number; color: string }[] });

const { innerWidth, innerHeight, margin, createSvg, getChartColors, getColor, onResize, wrapUpdate } = useD3Chart(containerRef, {
    top: 10,
    right: 10,
    bottom: 30,
    left: 45,
});

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const textColor = getColor('--muted-foreground');
    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);

    // Extract category keys (everything except 'date')
    const keys = Object.keys(props.data[0]).filter((k) => k !== 'date');
    const colors = getChartColors(keys.length);
    const colorMap = new Map(keys.map((k, i) => [k, colors[i % colors.length]]));

    const stack = d3.stack<StackedBarDatum>().keys(keys).order(d3.stackOrderNone).offset(d3.stackOffsetNone);

    const series = stack(props.data);

    const x = d3
        .scaleBand<string>()
        .domain(props.data.map((d) => d.date))
        .range([0, innerWidth.value])
        .padding(0.2);

    const y = d3
        .scaleLinear()
        .domain([0, d3.max(series, (s) => d3.max(s, (d) => d[1])) ?? 1])
        .nice()
        .range([innerHeight.value, 0]);

    // X axis
    const tickCount = Math.max(1, Math.floor(innerWidth.value / 80));
    const tickValues = props.data
        .map((d) => d.date)
        .filter((_, i, arr) => i % Math.max(1, Math.ceil(arr.length / tickCount)) === 0);

    g.append('g')
        .attr('transform', `translate(0,${innerHeight.value})`)
        .call(d3.axisBottom(x).tickValues(tickValues).tickSize(0).tickPadding(8))
        .call((g) => g.select('.domain').remove())
        .selectAll('text')
        .style('fill', textColor)
        .style('font-size', '10px')
        .attr('transform', 'rotate(-30)')
        .attr('text-anchor', 'end');

    // Y axis
    g.append('g')
        .call(d3.axisLeft(y).ticks(5).tickSize(-innerWidth.value).tickPadding(8))
        .call((g) => g.select('.domain').remove())
        .call((g) => g.selectAll('.tick line').attr('stroke', textColor).attr('stroke-opacity', 0.15))
        .selectAll('text')
        .style('fill', textColor)
        .style('font-size', '11px');

    // Stacked bars
    const layers = g
        .selectAll('.layer')
        .data(series)
        .enter()
        .append('g')
        .attr('class', 'layer')
        .attr('fill', (d) => colorMap.get(d.key) ?? colors[0]);

    layers
        .selectAll('rect')
        .data((d) => d)
        .enter()
        .append('rect')
        .attr('x', (d) => x(d.data.date) ?? 0)
        .attr('width', x.bandwidth())
        .attr('rx', 2)
        .attr('y', innerHeight.value)
        .attr('height', 0)
        .transition()
        .duration(500)
        .delay((_, i) => i * 30)
        .ease(d3.easeBackOut.overshoot(0.3))
        .attr('y', (d) => y(d[1]))
        .attr('height', (d) => Math.max(0, y(d[0]) - y(d[1])));

    // Interaction — hover columns by date
    g.selectAll('.hover-rect')
        .data(props.data)
        .enter()
        .append('rect')
        .attr('class', 'hover-rect')
        .attr('x', (d) => x(d.date) ?? 0)
        .attr('width', x.bandwidth())
        .attr('y', 0)
        .attr('height', innerHeight.value)
        .attr('fill', 'transparent')
        .on('mouseenter', function (event: MouseEvent, d) {
            const entries = keys
                .filter((k) => (d[k] as number) > 0)
                .map((k) => ({ key: k, value: d[k] as number, color: colorMap.get(k) ?? colors[0] }))
                .sort((a, b) => b.value - a.value);
            tooltip.value = { visible: true, x: event.clientX + 12, y: event.clientY - 10, date: d.date, entries };
        })
        .on('mousemove', function (event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function () {
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
                <div class="text-xs font-medium">{{ tooltip.date }}</div>
                <div v-for="entry in tooltip.entries" :key="entry.key" class="flex items-center gap-1.5 text-xs">
                    <span class="inline-block size-2 rounded-full" :style="{ backgroundColor: entry.color }" />
                    <span>{{ entry.key }}</span>
                    <span class="ml-auto tabular-nums text-muted-foreground">{{ entry.value }}</span>
                </div>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
