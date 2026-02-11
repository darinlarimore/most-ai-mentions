<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import * as d3 from 'd3';
import cloud from 'd3-cloud';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface WordCloudDatum {
    label: string;
    value: number;
}

const props = defineProps<{
    data: WordCloudDatum[];
    color?: string;
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', value: 0 });

const { width, height, createSvg, getChartColors, getColor, onResize } = useD3Chart(containerRef, {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
});

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const w = width.value;
    const h = height.value;
    if (w === 0 || h === 0) return;

    const colors = getChartColors(Math.min(props.data.length, 8));
    const maxVal = d3.max(props.data, (d) => d.value) ?? 1;

    const fontScale = d3
        .scaleSqrt()
        .domain([0, maxVal])
        .range([10, Math.min(w, h) / 6]);

    const words = props.data.map((d) => ({
        text: d.label,
        size: fontScale(d.value),
        value: d.value,
    }));

    cloud()
        .size([w, h])
        .words(words as any)
        .padding(3)
        .rotate(() => (Math.random() > 0.5 ? 0 : 90))
        .font('system-ui, sans-serif')
        .fontSize((d: any) => d.size)
        .on('end', (placed: any[]) => {
            const svg = createSvg();
            if (!svg) return;

            const g = svg.append('g').attr('transform', `translate(${w / 2},${h / 2})`);

            g.selectAll('text')
                .data(placed)
                .enter()
                .append('text')
                .style('font-family', 'system-ui, sans-serif')
                .style('cursor', 'default')
                .attr('text-anchor', 'middle')
                .attr('font-size', (d: any) => `${d.size}px`)
                .attr('font-weight', (d: any) => (d.size > 24 ? '700' : '500'))
                .attr('fill', (_: any, i: number) => colors[i % colors.length])
                .attr('transform', (d: any) => `translate(${d.x},${d.y}) rotate(${d.rotate})`)
                .text((d: any) => d.text)
                .attr('opacity', 0)
                .transition()
                .duration(600)
                .delay((_: any, i: number) => i * 20)
                .attr('opacity', 1);

            // Add tooltip interactions after transition
            g.selectAll('text')
                .style('cursor', 'pointer')
                .on('mouseenter', function (event: MouseEvent, d: any) {
                    d3.select(this).transition().duration(150).attr('font-size', `${d.size * 1.15}px`);
                    tooltip.value = {
                        visible: true,
                        x: event.clientX + 12,
                        y: event.clientY - 10,
                        label: d.text,
                        value: d.value,
                    };
                })
                .on('mousemove', function (event: MouseEvent) {
                    tooltip.value.x = event.clientX + 12;
                    tooltip.value.y = event.clientY - 10;
                })
                .on('mouseleave', function (_: MouseEvent, d: any) {
                    d3.select(this).transition().duration(150).attr('font-size', `${d.size}px`);
                    tooltip.value.visible = false;
                });
        })
        .start();
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
                <span class="ml-2 tabular-nums text-muted-foreground">{{ tooltip.value }} sites</span>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
