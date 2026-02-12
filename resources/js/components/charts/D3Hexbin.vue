<script setup lang="ts">
import * as d3 from 'd3';
import { hexbin as d3Hexbin } from 'd3-hexbin';
import { ref, onMounted, watch } from 'vue';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface HexbinDatum {
    label: string;
    x: number;
    y: number;
    slug?: string;
}

const props = defineProps<{
    data: HexbinDatum[];
    xLabel?: string;
    yLabel?: string;
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, count: 0, domains: '' });

const { innerWidth, innerHeight, margin, createSvg, drawCount, getColor, onResize, wrapUpdate } = useD3Chart(containerRef, {
    top: 10,
    right: 20,
    bottom: 40,
    left: 50,
});

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const textColor = getColor('--muted-foreground');
    const colorStart = getColor('--chart-1');
    const colorEnd = getColor('--chart-3');

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

    // Hexbin
    const hexRadius = Math.max(12, Math.min(innerWidth.value, innerHeight.value) / 20);

    const hexbinGenerator = d3Hexbin<[number, number]>()
        .x((d) => d[0])
        .y((d) => d[1])
        .radius(hexRadius)
        .extent([
            [0, 0],
            [innerWidth.value, innerHeight.value],
        ]);

    const points: [number, number][] = props.data.map((d) => [x(d.x), y(d.y)]);
    const bins = hexbinGenerator(points);

    const maxCount = d3.max(bins, (b) => b.length) ?? 1;
    const colorScale = d3.scaleLinear<string>().domain([0, maxCount]).range([colorStart, colorEnd]);

    // Build a lookup from binned points back to original data
    const pointToData = new Map<string, HexbinDatum>();
    props.data.forEach((d) => {
        pointToData.set(`${x(d.x)},${y(d.y)}`, d);
    });

    const animate = drawCount.value === 1;
    const hexagons = g
        .selectAll('.hex')
        .data(bins)
        .enter()
        .append('path')
        .attr('class', 'hex')
        .attr('transform', (d) => `translate(${d.x},${d.y})`)
        .attr('d', hexbinGenerator.hexagon())
        .attr('fill', (d) => colorScale(d.length))
        .attr('fill-opacity', animate ? 0 : 0.8)
        .attr('stroke', textColor)
        .attr('stroke-opacity', 0.15)
        .style('cursor', 'pointer');

    if (animate) {
        hexagons
            .transition()
            .duration(600)
            .delay((_, i) => i * 20)
            .ease(d3.easeQuadOut)
            .attr('fill-opacity', 0.8);
    }

    // Interaction
    hexagons
        .on('mouseenter', function (event: MouseEvent, d) {
            d3.select(this).transition().duration(150).attr('fill-opacity', 1);
            hexagons.filter((other) => other !== d).transition().duration(150).attr('fill-opacity', 0.3);
            const domains = d
                .map((pt) => pointToData.get(`${pt[0]},${pt[1]}`)?.label)
                .filter(Boolean)
                .slice(0, 5)
                .join(', ');
            tooltip.value = {
                visible: true,
                x: event.clientX + 12,
                y: event.clientY - 10,
                count: d.length,
                domains,
            };
        })
        .on('mousemove', function (event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function () {
            hexagons.transition().duration(200).attr('fill-opacity', 0.8);
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
                <div class="flex flex-col gap-0.5">
                    <span class="font-medium">{{ tooltip.count }} site{{ tooltip.count !== 1 ? 's' : '' }}</span>
                    <span v-if="tooltip.domains" class="text-xs text-muted-foreground">{{ tooltip.domains }}</span>
                </div>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
