<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import * as d3 from 'd3';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface DonutDatum {
    label: string;
    value: number;
}

const props = defineProps<{
    data: DonutDatum[];
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', value: 0, pct: '' });
const legendItems = ref<{ label: string; color: string; value: number }[]>([]);

const { width, height, createSvg, getChartColors, onResize } = useD3Chart(containerRef, {
    top: 10,
    right: 10,
    bottom: 10,
    left: 10,
});

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const colors = getChartColors(props.data.length);
    const total = d3.sum(props.data, (d) => d.value);

    // Use only the left portion for the chart, leave right for legend
    const chartWidth = width.value * 0.55;
    const radius = Math.min(chartWidth, height.value) / 2 - 10;
    const innerRadius = radius * 0.6;

    const g = svg.append('g').attr('transform', `translate(${chartWidth / 2},${height.value / 2})`);

    const pie = d3
        .pie<DonutDatum>()
        .value((d) => d.value)
        .sort(null)
        .padAngle(0.02);

    const arc = d3.arc<d3.PieArcDatum<DonutDatum>>().innerRadius(innerRadius).outerRadius(radius).cornerRadius(3);
    const arcHover = d3
        .arc<d3.PieArcDatum<DonutDatum>>()
        .innerRadius(innerRadius)
        .outerRadius(radius + 6)
        .cornerRadius(3);

    const arcs = g
        .selectAll('.arc')
        .data(pie(props.data))
        .enter()
        .append('path')
        .attr('class', 'arc')
        .attr('fill', (_, i) => colors[i % colors.length])
        .style('cursor', 'pointer');

    // Animate arcs from 0 angle
    arcs.transition()
        .duration(800)
        .ease(d3.easeQuadOut)
        .attrTween('d', function (d) {
            const interpolate = d3.interpolate({ startAngle: d.startAngle, endAngle: d.startAngle }, d);
            return (t: number) => arc(interpolate(t)) ?? '';
        });

    // Interaction
    arcs.on('mouseenter', function (event: MouseEvent, d) {
        d3.select(this).transition().duration(150).attr('d', (d: any) => arcHover(d) ?? '');
        arcs.filter((other) => other !== d).transition().duration(150).style('opacity', 0.4);
        const pct = total > 0 ? ((d.data.value / total) * 100).toFixed(1) : '0';
        tooltip.value = {
            visible: true,
            x: event.clientX + 12,
            y: event.clientY - 10,
            label: d.data.label,
            value: d.data.value,
            pct,
        };
    })
        .on('mousemove', function (event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function () {
            arcs.transition().duration(200).attr('d', (d: any) => arc(d) ?? '').style('opacity', 1);
            tooltip.value.visible = false;
        });

    // Update legend
    legendItems.value = props.data.map((d, i) => ({
        label: d.label,
        color: colors[i % colors.length],
        value: d.value,
    }));
}

onResize(draw);
onMounted(draw);
watch(() => props.data, draw, { deep: true });
</script>

<template>
    <div class="flex h-full w-full items-center justify-center">
        <div ref="containerRef" class="h-full min-w-0 flex-1" />
        <div v-if="legendItems.length" class="flex max-h-full flex-col gap-1.5 overflow-y-auto pr-2 pl-2">
            <div v-for="item in legendItems" :key="item.label" class="flex items-center gap-2 text-xs">
                <span class="inline-block size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: item.color }" />
                <span class="truncate text-muted-foreground">{{ item.label }}</span>
                <span class="ml-auto tabular-nums font-medium">{{ item.value }}</span>
            </div>
        </div>
        <Teleport to="body">
            <ChartTooltip :visible="tooltip.visible" :x="tooltip.x" :y="tooltip.y">
                <span class="font-medium">{{ tooltip.label }}</span>
                <span class="ml-2 tabular-nums text-muted-foreground">{{ tooltip.value }} ({{ tooltip.pct }}%)</span>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
