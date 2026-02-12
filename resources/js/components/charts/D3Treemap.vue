<script setup lang="ts">
import * as d3 from 'd3';
import { ref, onMounted, watch } from 'vue';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface TreemapDatum {
    label: string;
    value: number;
}

const props = defineProps<{
    data: TreemapDatum[];
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', value: 0 });

const { width, height, createSvg, getChartColors, getColor, onResize, wrapUpdate } = useD3Chart(containerRef, {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
});

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const colors = getChartColors(props.data.length);
    const textColor = getColor('--foreground');

    // Build hierarchy
    const root = d3
        .hierarchy({ children: props.data.map((d) => ({ ...d })) })
        .sum((d: any) => d.value ?? 0)
        .sort((a, b) => (b.value ?? 0) - (a.value ?? 0));

    // Create treemap layout
    d3.treemap<any>()
        .size([width.value, height.value])
        .tile(d3.treemapSquarify.ratio(1))
        .paddingInner(2)
        .paddingOuter(1)
        .round(true)(root);

    // Create groups for each leaf
    const leaves = svg
        .selectAll('g')
        .data(root.leaves())
        .enter()
        .append('g')
        .attr('transform', (d: any) => `translate(${d.x0},${d.y0})`);

    // Add clip paths for text
    leaves
        .append('clipPath')
        .attr('id', (_, i) => `treemap-clip-${i}`)
        .append('rect')
        .attr('width', (d: any) => d.x1 - d.x0)
        .attr('height', (d: any) => d.y1 - d.y0);

    // Rectangles
    const rects = leaves
        .append('rect')
        .attr('width', (d: any) => d.x1 - d.x0)
        .attr('height', (d: any) => d.y1 - d.y0)
        .attr('fill', (_, i) => colors[i % colors.length])
        .attr('fill-opacity', 0)
        .attr('rx', 3);

    // Animate fade in with stagger
    rects
        .transition()
        .duration(500)
        .delay((_, i) => i * 20)
        .ease(d3.easeQuadOut)
        .attr('fill-opacity', 0.85);

    // Labels - only show if cell is large enough
    leaves
        .append('text')
        .attr('clip-path', (_, i) => `url(#treemap-clip-${i})`)
        .selectAll('tspan')
        .data((d: any) => {
            const w = d.x1 - d.x0;
            const h = d.y1 - d.y0;
            if (w < 40 || h < 28) return [];
            const label = d.data.label ?? '';
            const value = d.value?.toLocaleString() ?? '';
            return h >= 42 ? [label, value] : [label];
        })
        .enter()
        .append('tspan')
        .attr('x', 5)
        .attr('y', (_, i) => 16 + i * 14)
        .attr('fill', textColor)
        .attr('font-size', '12px')
        .attr('font-weight', (_, i) => (i === 0 ? '600' : '400'))
        .attr('opacity', (_, i) => (i === 0 ? 1 : 0.7))
        .text((d: any) => d);

    // Interactions
    leaves
        .on('mouseenter', function (event: MouseEvent, d: any) {
            d3.select(this).select('rect').transition().duration(150).attr('fill-opacity', 1);
            leaves
                .filter((other) => other !== d)
                .select('rect')
                .transition()
                .duration(150)
                .attr('fill-opacity', 0.4);
            tooltip.value = {
                visible: true,
                x: event.clientX + 12,
                y: event.clientY - 10,
                label: d.data.label,
                value: d.value ?? 0,
            };
        })
        .on('mousemove', function (event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function () {
            leaves.select('rect').transition().duration(200).attr('fill-opacity', 0.85);
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
