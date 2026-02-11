<script setup lang="ts">
import * as d3 from 'd3';
import { ref, onMounted, watch } from 'vue';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface LollipopDatum {
    label: string;
    value: number;
}

const props = defineProps<{
    data: LollipopDatum[];
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', value: 0 });

const { innerWidth, innerHeight, margin, createSvg, getChartColors, getColor, onResize } = useD3Chart(containerRef, {
    top: 10,
    right: 20,
    bottom: 20,
    left: 120,
});

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const colors = getChartColors(props.data.length);
    const textColor = getColor('--muted-foreground');

    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);

    const y = d3
        .scaleBand<string>()
        .domain(props.data.map((d) => d.label))
        .range([0, innerHeight.value])
        .padding(0.35);

    const x = d3
        .scaleLinear()
        .domain([0, d3.max(props.data, (d) => d.value) ?? 1])
        .nice()
        .range([0, innerWidth.value]);

    // Y axis
    g.append('g')
        .call(d3.axisLeft(y).tickSize(0).tickPadding(8))
        .call((g) => g.select('.domain').remove())
        .selectAll('text')
        .style('fill', textColor)
        .style('font-size', '12px');

    // X axis
    g.append('g')
        .attr('transform', `translate(0,${innerHeight.value})`)
        .call(d3.axisBottom(x).ticks(5).tickSize(-innerHeight.value).tickPadding(8))
        .call((g) => g.select('.domain').remove())
        .call((g) => g.selectAll('.tick line').attr('stroke', textColor).attr('stroke-opacity', 0.15))
        .selectAll('text')
        .style('fill', textColor)
        .style('font-size', '11px');

    const bandMid = y.bandwidth() / 2;

    // Lines (stems)
    const lines = g
        .selectAll('.stem')
        .data(props.data)
        .enter()
        .append('line')
        .attr('class', 'stem')
        .attr('x1', 0)
        .attr('x2', 0)
        .attr('y1', (d) => (y(d.label) ?? 0) + bandMid)
        .attr('y2', (d) => (y(d.label) ?? 0) + bandMid)
        .attr('stroke', (_, i) => colors[i % colors.length])
        .attr('stroke-width', 2);

    // Animate lines
    lines
        .transition()
        .duration(600)
        .delay((_, i) => i * 30)
        .ease(d3.easeQuadOut)
        .attr('x2', (d) => x(d.value));

    // Circles (endpoints)
    const dots = g
        .selectAll('.lollipop-dot')
        .data(props.data)
        .enter()
        .append('circle')
        .attr('class', 'lollipop-dot')
        .attr('cx', 0)
        .attr('cy', (d) => (y(d.label) ?? 0) + bandMid)
        .attr('r', 5)
        .attr('fill', (_, i) => colors[i % colors.length])
        .style('cursor', 'pointer');

    // Animate dots
    dots.transition()
        .duration(600)
        .delay((_, i) => i * 30)
        .ease(d3.easeQuadOut)
        .attr('cx', (d) => x(d.value));

    // Interaction
    dots.on('mouseenter', function (event: MouseEvent, d) {
        d3.select(this).transition().duration(150).attr('r', 8);
        lines.filter((other) => other !== d).transition().duration(150).style('opacity', 0.25);
        dots.filter((other) => other !== d).transition().duration(150).style('opacity', 0.25);
        tooltip.value = { visible: true, x: event.clientX + 12, y: event.clientY - 10, label: d.label, value: d.value };
    })
        .on('mousemove', function (event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function () {
            dots.transition().duration(200).attr('r', 5);
            lines.transition().duration(200).style('opacity', 1);
            dots.transition().duration(200).style('opacity', 1);
            tooltip.value.visible = false;
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
                <span class="font-medium">{{ tooltip.label }}</span>
                <span class="ml-2 tabular-nums text-muted-foreground">{{ tooltip.value.toLocaleString() }}</span>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
