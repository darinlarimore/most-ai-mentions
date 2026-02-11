<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import * as d3 from 'd3';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface RadialTreeDatum {
    label: string;
    value: number;
}

const props = defineProps<{
    data: RadialTreeDatum[];
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', value: 0 });

const { width, height, createSvg, getChartColors, getColor, onResize } = useD3Chart(containerRef, {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
});

// Categorize technologies for grouping
const TECH_CATEGORIES: Record<string, string> = {
    React: 'Frontend',
    'Next.js': 'Frontend',
    Vue: 'Frontend',
    Nuxt: 'Frontend',
    Angular: 'Frontend',
    Svelte: 'Frontend',
    Gatsby: 'Frontend',
    Astro: 'Frontend',
    jQuery: 'Frontend',
    'Alpine.js': 'Frontend',
    HTMX: 'Frontend',
    'Tailwind CSS': 'CSS',
    Bootstrap: 'CSS',
    WordPress: 'CMS',
    Drupal: 'CMS',
    Joomla: 'CMS',
    Ghost: 'CMS',
    Wix: 'CMS',
    Squarespace: 'CMS',
    Webflow: 'CMS',
    Shopify: 'CMS',
    Hugo: 'Static',
    Jekyll: 'Static',
    Cloudflare: 'Infra',
    Vercel: 'Infra',
    Netlify: 'Infra',
    Nginx: 'Infra',
    Apache: 'Infra',
    PHP: 'Backend',
    'Node.js': 'Backend',
    'ASP.NET': 'Backend',
};

function buildHierarchy(data: RadialTreeDatum[]) {
    const grouped: Record<string, { label: string; value: number }[]> = {};
    for (const item of data) {
        const category = TECH_CATEGORIES[item.label] ?? 'Other';
        if (!grouped[category]) grouped[category] = [];
        grouped[category].push(item);
    }

    return {
        label: 'Tech Stack',
        children: Object.entries(grouped).map(([category, items]) => ({
            label: category,
            children: items.map((item) => ({
                label: item.label,
                value: item.value,
            })),
        })),
    };
}

function draw() {
    if (!containerRef.value || !props.data?.length) return;

    const svg = createSvg();
    if (!svg) return;

    const textColor = getColor('--muted-foreground');
    const linkColor = getColor('--border');

    const hierarchyData = buildHierarchy(props.data);
    const root = d3.hierarchy(hierarchyData);

    const radius = Math.min(width.value, height.value) / 2 - 40;

    const tree = d3
        .tree<any>()
        .size([2 * Math.PI, radius])
        .separation((a, b) => (a.parent === b.parent ? 1 : 2) / a.depth);

    tree(root);

    // Get unique categories for color mapping
    const categories = [...new Set(props.data.map((d) => TECH_CATEGORIES[d.label] ?? 'Other'))];
    const colors = getChartColors(categories.length);
    const colorMap = new Map(categories.map((c, i) => [c, colors[i]]));

    const g = svg.append('g').attr('transform', `translate(${width.value / 2},${height.value / 2})`);

    // Draw links
    const links = g
        .append('g')
        .selectAll('path')
        .data(root.links())
        .enter()
        .append('path')
        .attr('fill', 'none')
        .attr('stroke', linkColor)
        .attr('stroke-width', 1.5)
        .attr('stroke-opacity', 0)
        .attr(
            'd',
            d3
                .linkRadial<any, any>()
                .angle((d) => d.x)
                .radius((d) => d.y),
        );

    // Animate links
    links.transition().duration(600).ease(d3.easeQuadOut).attr('stroke-opacity', 0.5);

    // Draw nodes
    const nodes = g
        .append('g')
        .selectAll('g')
        .data(root.descendants())
        .enter()
        .append('g')
        .attr('transform', (d: any) => `rotate(${(d.x * 180) / Math.PI - 90}) translate(${d.y},0)`);

    // Node circles
    const circles = nodes
        .append('circle')
        .attr('r', (d: any) => {
            if (d.depth === 0) return 5;
            if (d.depth === 1) return 4;
            return Math.max(3, Math.min(8, Math.sqrt(d.data.value ?? 1) * 1.5));
        })
        .attr('fill', (d: any) => {
            if (d.depth === 0) return getColor('--foreground');
            if (d.depth === 1) return colorMap.get(d.data.label) ?? textColor;
            const category = TECH_CATEGORIES[d.data.label] ?? 'Other';
            return colorMap.get(category) ?? textColor;
        })
        .attr('fill-opacity', 0)
        .attr('stroke', 'none');

    // Animate nodes
    circles
        .transition()
        .duration(400)
        .delay((_, i) => i * 15)
        .ease(d3.easeBackOut)
        .attr('fill-opacity', 0.85);

    // Node labels
    nodes
        .append('text')
        .attr('dy', '0.32em')
        .attr('x', (d: any) => (d.x < Math.PI === !d.children ? 8 : -8))
        .attr('text-anchor', (d: any) => (d.x < Math.PI === !d.children ? 'start' : 'end'))
        .attr('transform', (d: any) => (d.x >= Math.PI ? 'rotate(180)' : null))
        .attr('fill', textColor)
        .attr('font-size', (d: any) => (d.depth <= 1 ? '12px' : '11px'))
        .attr('font-weight', (d: any) => (d.depth <= 1 ? '600' : '400'))
        .attr('opacity', 0)
        .text((d: any) => d.data.label)
        .transition()
        .duration(400)
        .delay((_, i) => 300 + i * 10)
        .attr('opacity', 1);

    // Interaction on leaf nodes
    nodes
        .filter((d: any) => !d.children)
        .style('cursor', 'pointer')
        .on('mouseenter', function (event: MouseEvent, d: any) {
            d3.select(this).select('circle').transition().duration(150).attr('fill-opacity', 1).attr('r', (d: any) => {
                return Math.max(5, Math.min(10, Math.sqrt(d.data.value ?? 1) * 2));
            });
            tooltip.value = {
                visible: true,
                x: event.clientX + 12,
                y: event.clientY - 10,
                label: d.data.label,
                value: d.data.value ?? 0,
            };
        })
        .on('mousemove', function (event: MouseEvent) {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function () {
            d3.select(this)
                .select('circle')
                .transition()
                .duration(200)
                .attr('fill-opacity', 0.85)
                .attr('r', (d: any) => Math.max(3, Math.min(8, Math.sqrt(d.data.value ?? 1) * 1.5)));
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
                <span class="ml-2 tabular-nums text-muted-foreground">{{ tooltip.value }} sites</span>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
