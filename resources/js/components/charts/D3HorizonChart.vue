<script setup lang="ts">
import * as d3 from 'd3';
import { ref, onMounted, watch } from 'vue';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface HorizonDatum {
    date: string;
    value: number;
}

const props = defineProps<{
    data: HorizonDatum[];
    bands?: number;
    label?: string;
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, date: '', value: 0 });

const { width, height, margin, createSvg, getColor, onResize } = useD3Chart(containerRef, {
    top: 5,
    right: 10,
    bottom: 25,
    left: 10,
});

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const bands = props.bands ?? 4;
    const textColor = getColor('--muted-foreground');
    const baseColor = getColor('--chart-1');

    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);
    const innerW = width.value - margin.left - margin.right;
    const innerH = height.value - margin.top - margin.bottom;

    // Parse dates and create scales
    const parsed = props.data.map((d) => ({
        date: new Date(d.date),
        value: d.value,
    }));

    const x = d3
        .scaleTime()
        .domain(d3.extent(parsed, (d) => d.date) as [Date, Date])
        .range([0, innerW]);

    const maxVal = d3.max(parsed, (d) => d.value) ?? 1;
    const bandHeight = innerH;
    const step = maxVal / bands;

    // Create color scale — progressively darker/more saturated bands
    const colorScale = d3
        .scaleLinear<string>()
        .domain([0, bands - 1])
        .range([d3.color(baseColor)!.copy({ opacity: 0.2 }).formatRgb(), baseColor])
        .interpolate(d3.interpolateRgb);

    // Create area generator
    const area = d3
        .area<(typeof parsed)[0]>()
        .x((d) => x(d.date))
        .curve(d3.curveBasis)
        .defined((d) => !isNaN(d.value));

    // Draw each band layer
    for (let band = 0; band < bands; band++) {
        const bandMin = band * step;

        area.y0(bandHeight).y1((d) => {
            const clamped = Math.max(0, Math.min(step, d.value - bandMin));
            return bandHeight - (clamped / step) * bandHeight;
        });

        g.append('path')
            .datum(parsed)
            .attr('fill', colorScale(band))
            .attr('d', area)
            .attr('opacity', 0)
            .transition()
            .duration(600)
            .delay(band * 100)
            .ease(d3.easeQuadOut)
            .attr('opacity', 1);
    }

    // X axis
    g.append('g')
        .attr('transform', `translate(0,${innerH})`)
        .call(
            d3
                .axisBottom(x)
                .ticks(d3.timeWeek.every(1))
                .tickFormat(d3.timeFormat('%b %d') as any)
                .tickSize(0)
                .tickPadding(6),
        )
        .call((g) => g.select('.domain').remove())
        .selectAll('text')
        .style('fill', textColor)
        .style('font-size', '10px');

    // Label
    if (props.label) {
        g.append('text')
            .attr('x', 4)
            .attr('y', 14)
            .attr('fill', textColor)
            .attr('font-size', '11px')
            .attr('font-weight', '600')
            .text(props.label);
    }

    // Invisible overlay for tooltip
    g.append('rect')
        .attr('width', innerW)
        .attr('height', innerH)
        .attr('fill', 'transparent')
        .style('cursor', 'crosshair')
        .on('mousemove', function (event: MouseEvent) {
            const [mx] = d3.pointer(event);
            const date = x.invert(mx);
            // Find nearest data point
            const bisect = d3.bisector((d: (typeof parsed)[0]) => d.date).left;
            const idx = bisect(parsed, date, 1);
            const d0 = parsed[idx - 1];
            const d1 = parsed[idx];
            const nearest = d1 && date.getTime() - d0.date.getTime() > d1.date.getTime() - date.getTime() ? d1 : d0;
            if (nearest) {
                tooltip.value = {
                    visible: true,
                    x: event.clientX + 12,
                    y: event.clientY - 10,
                    date: d3.timeFormat('%b %d, %Y')(nearest.date),
                    value: nearest.value,
                };
            }
        })
        .on('mouseleave', function () {
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
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs text-muted-foreground">{{ tooltip.date }}</span>
                    <span class="font-medium tabular-nums">Avg score: {{ Math.round(tooltip.value).toLocaleString() }}</span>
                </div>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
