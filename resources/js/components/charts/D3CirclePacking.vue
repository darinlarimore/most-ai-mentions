<script setup lang="ts">
import * as d3 from 'd3';
import { ref, onMounted, watch } from 'vue';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface CirclePackDatum {
    label: string;
    value: number;
}

const props = defineProps<{
    data: CirclePackDatum[];
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', value: 0, pct: '' });

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

    const size = Math.min(width.value, height.value);

    const root = d3
        .hierarchy({ children: props.data.map((d) => ({ ...d })) } as any)
        .sum((d: any) => d.value ?? 0)
        .sort((a, b) => (b.value ?? 0) - (a.value ?? 0));

    const pack = d3.pack<any>().size([size - 20, size - 20]).padding(4);
    pack(root);

    const g = svg
        .append('g')
        .attr('transform', `translate(${(width.value - size) / 2 + 10},${(height.value - size) / 2 + 10})`);

    const leaves = root.leaves();

    // Circles
    const circles = g
        .selectAll('.bubble')
        .data(leaves)
        .enter()
        .append('circle')
        .attr('class', 'bubble')
        .attr('cx', (d: any) => d.x)
        .attr('cy', (d: any) => d.y)
        .attr('fill', (_, i) => colors[i % colors.length])
        .attr('fill-opacity', 0.8)
        .style('cursor', 'pointer')
        .attr('r', 0);

    // Animate
    circles
        .transition()
        .duration(600)
        .delay((_, i) => i * 20)
        .ease(d3.easeBackOut.overshoot(0.5))
        .attr('r', (d: any) => d.r);

    // Labels inside circles
    g.selectAll('.bubble-label')
        .data(leaves.filter((d: any) => d.r > 20))
        .enter()
        .append('text')
        .attr('class', 'bubble-label')
        .attr('x', (d: any) => d.x)
        .attr('y', (d: any) => d.y)
        .attr('text-anchor', 'middle')
        .attr('dy', '0.35em')
        .style('font-size', (d: any) => `${Math.min(d.r / 3, 12)}px`)
        .style('fill', '#fff')
        .style('pointer-events', 'none')
        .style('opacity', 0)
        .text((d) => d.data.label)
        .transition()
        .delay(600)
        .duration(200)
        .style('opacity', 1);

    // Interaction
    circles
        .on('mouseenter', function (event: MouseEvent, d) {
            d3.select(this)
                .transition()
                .duration(150)
                .attr('r', (d: any) => d.r * 1.08)
                .attr('fill-opacity', 1);
            circles.filter((other) => other !== d).transition().duration(150).attr('fill-opacity', 0.3);
            const pct = total > 0 ? ((d.value! / total) * 100).toFixed(1) : '0';
            tooltip.value = {
                visible: true,
                x: event.clientX + 12,
                y: event.clientY - 10,
                label: d.data.label,
                value: d.value!,
                pct,
            };
        })
        .on('mousemove', function (event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function () {
            circles
                .transition()
                .duration(200)
                .attr('r', (d: any) => d.r)
                .attr('fill-opacity', 0.8);
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
                <span class="ml-2 tabular-nums text-muted-foreground">{{ tooltip.value }} ({{ tooltip.pct }}%)</span>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
