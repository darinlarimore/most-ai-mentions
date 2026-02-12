<script setup lang="ts">
import * as d3 from 'd3';
import cloud from 'd3-cloud';
import { ref, onMounted, watch } from 'vue';
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

const { width, height, createSvg, drawCount, getChartColors, onResize, wrapUpdate } = useD3Chart(containerRef, {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
});

/** Previous values keyed by label — used to detect growth for bounce animation. */
let prevValues = new Map<string, number>();
let svgGroup: d3.Selection<SVGGElement, unknown, null, undefined> | null = null;

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const w = width.value;
    const h = height.value;
    if (w === 0 || h === 0) return;

    const colors = getChartColors(Math.min(props.data.length, 8));
    const maxVal = d3.max(props.data, (d) => d.value) ?? 1;
    const isFirstDraw = drawCount.value === 0;

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
        .rotate((d: any) => {
            // Deterministic rotation based on label so layout stays stable across updates
            let hash = 0;
            for (let i = 0; i < (d.text?.length ?? 0); i++) {
                hash = (hash * 31 + d.text.charCodeAt(i)) | 0;
            }
            return (hash & 1) ? 90 : 0;
        })
        .font('system-ui, sans-serif')
        .fontSize((d: any) => d.size)
        .on('end', (placed: any[]) => {
            if (isFirstDraw || !svgGroup) {
                // Full draw — create fresh SVG
                const svg = createSvg();
                if (!svg) return;
                svgGroup = svg.append('g').attr('transform', `translate(${w / 2},${h / 2})`);
                renderFresh(placed, colors, isFirstDraw);
            } else {
                // Incremental update — transition existing words
                renderUpdate(placed, colors);
            }

            // Store current values for next comparison
            prevValues = new Map(placed.map((d: any) => [d.text, d.value]));
        })
        .start();
}

function renderFresh(placed: any[], colors: string[], animate: boolean) {
    if (!svgGroup) return;

    const texts = svgGroup.selectAll('text')
        .data(placed, (d: any) => d.text)
        .enter()
        .append('text')
        .style('font-family', 'system-ui, sans-serif')
        .style('cursor', 'pointer')
        .attr('text-anchor', 'middle')
        .attr('font-size', (d: any) => `${d.size}px`)
        .attr('font-weight', (d: any) => (d.size > 24 ? '700' : '500'))
        .attr('fill', (_: any, i: number) => colors[i % colors.length])
        .attr('transform', (d: any) => `translate(${d.x},${d.y}) rotate(${d.rotate})`)
        .text((d: any) => d.text)
        .attr('opacity', animate ? 0 : 1);

    if (animate) {
        texts
            .transition()
            .duration(600)
            .delay((_: any, i: number) => i * 20)
            .attr('opacity', 1);
    }

    attachTooltip();
}

function renderUpdate(placed: any[], colors: string[]) {
    if (!svgGroup) return;

    const join = svgGroup.selectAll<SVGTextElement, any>('text')
        .data(placed, (d: any) => d.text);

    // Exit — words no longer present
    join.exit()
        .transition()
        .duration(400)
        .attr('opacity', 0)
        .remove();

    // Update — existing words move and resize
    join
        .transition()
        .duration(600)
        .ease(d3.easeCubicOut)
        .attr('transform', (d: any) => `translate(${d.x},${d.y}) rotate(${d.rotate})`)
        .attr('font-size', (d: any) => `${d.size}px`)
        .attr('font-weight', (d: any) => (d.size > 24 ? '700' : '500'))
        .attr('fill', (_: any, i: number) => colors[i % colors.length]);

    // Bounce effect for words that grew
    join.each(function (d: any) {
        const prev = prevValues.get(d.text) ?? 0;
        if (d.value > prev && prev > 0) {
            const el = d3.select(this);
            const overshoot = Math.min(d.size * 1.3, d.size + 8);
            el.transition()
                .duration(300)
                .attr('font-size', `${overshoot}px`)
                .transition()
                .duration(400)
                .ease(d3.easeBackOut.overshoot(1.5))
                .attr('font-size', `${d.size}px`);
        }
    });

    // Enter — new words fade in
    join.enter()
        .append('text')
        .style('font-family', 'system-ui, sans-serif')
        .style('cursor', 'pointer')
        .attr('text-anchor', 'middle')
        .attr('font-size', (d: any) => `${d.size}px`)
        .attr('font-weight', (d: any) => (d.size > 24 ? '700' : '500'))
        .attr('fill', (_: any, i: number) => colors[i % colors.length])
        .attr('transform', (d: any) => `translate(${d.x},${d.y}) rotate(${d.rotate})`)
        .text((d: any) => d.text)
        .attr('opacity', 0)
        .transition()
        .duration(500)
        .attr('opacity', 1);

    attachTooltip();
}

function attachTooltip() {
    if (!svgGroup) return;

    svgGroup.selectAll<SVGTextElement, any>('text')
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
                <span class="ml-2 tabular-nums text-muted-foreground">{{ tooltip.value }} sites</span>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
