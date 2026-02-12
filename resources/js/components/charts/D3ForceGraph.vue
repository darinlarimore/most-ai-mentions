<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import * as d3 from 'd3';
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import { useD3Chart } from '@/composables/useD3Chart';
import ChartTooltip from './ChartTooltip.vue';

export interface GraphNode extends d3.SimulationNodeDatum {
    id: string;
    type: 'site' | 'term';
    label: string;
    slug?: string;
    category?: string;
    score?: number;
    count?: number;
}

export interface GraphLink extends d3.SimulationLinkDatum<GraphNode> {
    source: string | GraphNode;
    target: string | GraphNode;
}

export interface NetworkData {
    nodes: GraphNode[];
    links: GraphLink[];
}

const props = defineProps<{
    data: NetworkData;
}>();

const containerRef = ref<HTMLElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, label: '', detail: '' });

const { width, height, createSvg, getChartColors, getColor, onResize } = useD3Chart(containerRef, {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
});

let simulation: d3.Simulation<GraphNode, GraphLink> | null = null;
let svg: d3.Selection<SVGSVGElement, unknown, null, undefined> | null = null;
let linkG: d3.Selection<SVGGElement, unknown, null, undefined> | null = null;
let nodeG: d3.Selection<SVGGElement, unknown, null, undefined> | null = null;
let nodes: GraphNode[] = [];
let links: GraphLink[] = [];
let nodeMap: Map<string, GraphNode> = new Map();

function buildCategoryColorMap(): Map<string, string> {
    const categories = [...new Set(nodes.filter((n) => n.type === 'site').map((n) => n.category ?? 'other'))];
    const colors = getChartColors(Math.max(categories.length, 5));
    const map = new Map<string, string>();
    categories.forEach((cat, i) => map.set(cat, colors[i % colors.length]));
    return map;
}

function radiusForNode(n: GraphNode): number {
    if (n.type === 'term') {
        return Math.max(4, Math.sqrt(n.count ?? 1) * 3);
    }
    return Math.max(5, Math.sqrt(n.score ?? 1) * 0.4);
}

function draw() {
    if (!containerRef.value || !props.data) return;

    // Stop old simulation
    simulation?.stop();

    svg = createSvg();
    if (!svg) return;

    const w = width.value;
    const h = height.value;

    // Deep copy data so simulation can mutate — start all nodes at center
    nodes = props.data.nodes.map((n) => ({
        ...n,
        x: w / 2 + (Math.random() - 0.5) * 10,
        y: h / 2 + (Math.random() - 0.5) * 10,
    }));
    links = props.data.links.map((l) => ({ ...l }));
    nodeMap = new Map(nodes.map((n) => [n.id, n]));

    const categoryColors = buildCategoryColorMap();
    const termColor = getColor('--chart-2');
    const textColor = getColor('--muted-foreground');

    // Defs for diamond marker
    svg.append('defs')
        .append('symbol')
        .attr('id', 'diamond')
        .attr('viewBox', '-1 -1 2 2')
        .append('rect')
        .attr('x', -0.7)
        .attr('y', -0.7)
        .attr('width', 1.4)
        .attr('height', 1.4)
        .attr('transform', 'rotate(45)');

    linkG = svg.append('g').attr('class', 'links');
    nodeG = svg.append('g').attr('class', 'nodes');

    // Links — start invisible, fade in after nodes appear
    const linkSel = linkG
        .selectAll<SVGLineElement, GraphLink>('line')
        .data(links)
        .enter()
        .append('line')
        .attr('stroke', textColor)
        .attr('stroke-opacity', 0)
        .attr('stroke-width', 1);

    linkSel
        .transition()
        .duration(500)
        .delay(800)
        .attr('stroke-opacity', 0.08);

    // Nodes — grow from center with staggered entrance
    const nodeSel = nodeG
        .selectAll<SVGCircleElement, GraphNode>('circle')
        .data(nodes, (d) => d.id)
        .enter()
        .append('circle')
        .attr('cx', w / 2)
        .attr('cy', h / 2)
        .attr('r', 0)
        .attr('fill', (d) =>
            d.type === 'term' ? termColor : (categoryColors.get(d.category ?? 'other') ?? termColor),
        )
        .attr('fill-opacity', 0)
        .attr('stroke', (d) =>
            d.type === 'term' ? termColor : (categoryColors.get(d.category ?? 'other') ?? termColor),
        )
        .attr('stroke-width', 1)
        .attr('stroke-opacity', 0)
        .style('cursor', (d) => (d.type === 'site' ? 'pointer' : 'default'));

    // Staggered grow: site nodes first, then term nodes, with random spread within each group
    nodeSel
        .transition()
        .duration(700)
        .delay((d) => {
            const base = d.type === 'site' ? 0 : 300;
            return base + Math.random() * 500;
        })
        .ease(d3.easeBackOut.overshoot(1.3))
        .attr('r', (d) => radiusForNode(d))
        .attr('fill-opacity', (d) => (d.type === 'term' ? 0.7 : 0.8))
        .attr('stroke-opacity', 0.5);

    // Drag
    const drag = d3
        .drag<SVGCircleElement, GraphNode>()
        .on('start', (event, d) => {
            if (!event.active) simulation?.alphaTarget(0.3).restart();
            d.fx = d.x;
            d.fy = d.y;
        })
        .on('drag', (event, d) => {
            d.fx = event.x;
            d.fy = event.y;
        })
        .on('end', (event, d) => {
            if (!event.active) simulation?.alphaTarget(0);
            d.fx = null;
            d.fy = null;
        });

    nodeSel.call(drag);

    // Hover
    nodeSel
        .on('mouseenter', function (event: MouseEvent, d) {
            const connectedIds = new Set<string>();
            links.forEach((l) => {
                const s = typeof l.source === 'object' ? l.source.id : l.source;
                const t = typeof l.target === 'object' ? l.target.id : l.target;
                if (s === d.id) connectedIds.add(t);
                if (t === d.id) connectedIds.add(s);
            });
            connectedIds.add(d.id);

            nodeSel.attr('fill-opacity', (n) => (connectedIds.has(n.id) ? 1 : 0.1));
            linkSel.attr('stroke-opacity', (l) => {
                const s = typeof l.source === 'object' ? l.source.id : l.source;
                const t = typeof l.target === 'object' ? l.target.id : l.target;
                return s === d.id || t === d.id ? 0.4 : 0.02;
            });

            d3.select(this).transition().duration(150).attr('r', radiusForNode(d) * 1.4);

            const detail =
                d.type === 'site'
                    ? `Score: ${d.score ?? 0}`
                    : `${d.count ?? 0} sites`;

            tooltip.value = {
                visible: true,
                x: event.clientX + 12,
                y: event.clientY - 10,
                label: d.label,
                detail,
            };
        })
        .on('mousemove', (event: MouseEvent) => {
            tooltip.value.x = event.clientX + 12;
            tooltip.value.y = event.clientY - 10;
        })
        .on('mouseleave', function (_, d) {
            nodeSel.attr('fill-opacity', (n) => (n.type === 'term' ? 0.7 : 0.8));
            linkSel.attr('stroke-opacity', 0.08);
            d3.select(this).transition().duration(150).attr('r', radiusForNode(d));
            tooltip.value.visible = false;
        });

    // Click
    nodeSel.on('click', (_, d) => {
        if (d.type === 'site' && d.slug) {
            router.visit(`/sites/${d.slug}`);
        }
    });

    // Force simulation
    simulation = d3
        .forceSimulation<GraphNode>(nodes)
        .force(
            'link',
            d3
                .forceLink<GraphNode, GraphLink>(links)
                .id((d) => d.id)
                .distance(60),
        )
        .force('charge', d3.forceManyBody().strength(-80))
        .force('x', d3.forceX(w / 2).strength(0.05))
        .force('y', d3.forceY(h / 2).strength(0.05))
        .force('collide', d3.forceCollide<GraphNode>().radius((d) => radiusForNode(d) + 2))
        .on('tick', () => {
            linkSel
                .attr('x1', (d) => (d.source as GraphNode).x ?? 0)
                .attr('y1', (d) => (d.source as GraphNode).y ?? 0)
                .attr('x2', (d) => (d.target as GraphNode).x ?? 0)
                .attr('y2', (d) => (d.target as GraphNode).y ?? 0);

            nodeSel.attr('cx', (d) => d.x ?? 0).attr('cy', (d) => d.y ?? 0);
        });
}

/**
 * Called from real-time Echo events to add a new site + its terms.
 */
function addSiteNode(event: {
    site_id: number;
    domain: string;
    slug: string;
    category: string;
    hype_score: number;
    ai_terms: string[];
}) {
    if (!simulation || !linkG || !nodeG || !event.domain || !event.ai_terms?.length) return;

    const siteId = 'site:' + event.site_id;
    if (nodeMap.has(siteId)) return;

    const w = width.value;
    const h = height.value;
    const categoryColors = buildCategoryColorMap();
    const termColor = getColor('--chart-2');
    const textColor = getColor('--muted-foreground');

    const siteNode: GraphNode = {
        id: siteId,
        type: 'site',
        label: event.domain,
        slug: event.slug,
        category: event.category,
        score: event.hype_score,
        x: w / 2 + (Math.random() - 0.5) * 40,
        y: h / 2 + (Math.random() - 0.5) * 40,
    };

    nodes.push(siteNode);
    nodeMap.set(siteId, siteNode);

    for (const term of event.ai_terms) {
        const termId = 'term:' + term;
        if (!nodeMap.has(termId)) {
            const termNode: GraphNode = {
                id: termId,
                type: 'term',
                label: term,
                count: 1,
                x: w / 2 + (Math.random() - 0.5) * 40,
                y: h / 2 + (Math.random() - 0.5) * 40,
            };
            nodes.push(termNode);
            nodeMap.set(termId, termNode);
        } else {
            const existing = nodeMap.get(termId)!;
            existing.count = (existing.count ?? 0) + 1;
        }
        links.push({ source: siteId, target: termId });
    }

    // Rebind links
    const linkSel = linkG
        .selectAll<SVGLineElement, GraphLink>('line')
        .data(links);

    linkSel
        .enter()
        .append('line')
        .attr('stroke', textColor)
        .attr('stroke-opacity', 0)
        .attr('stroke-width', 1)
        .transition()
        .duration(600)
        .attr('stroke-opacity', 0.08);

    linkSel.exit().remove();

    // Rebind nodes
    const nodeSel = nodeG
        .selectAll<SVGCircleElement, GraphNode>('circle')
        .data(nodes, (d) => d.id);

    const newNodes = nodeSel
        .enter()
        .append('circle')
        .attr('r', 0)
        .attr('cx', (d) => d.x ?? w / 2)
        .attr('cy', (d) => d.y ?? h / 2)
        .attr('fill', (d) =>
            d.type === 'term' ? termColor : (categoryColors.get(d.category ?? 'other') ?? termColor),
        )
        .attr('fill-opacity', (d) => (d.type === 'term' ? 0.7 : 0.8))
        .attr('stroke', (d) =>
            d.type === 'term' ? termColor : (categoryColors.get(d.category ?? 'other') ?? termColor),
        )
        .attr('stroke-width', 1)
        .attr('stroke-opacity', 0.5)
        .style('cursor', (d) => (d.type === 'site' ? 'pointer' : 'default'));

    newNodes
        .transition()
        .duration(600)
        .ease(d3.easeBackOut)
        .attr('r', (d) => radiusForNode(d));

    // Update existing term nodes radius
    nodeSel.filter((d) => d.type === 'term').attr('r', (d) => radiusForNode(d));

    nodeSel.exit().remove();

    // Restart simulation
    simulation.nodes(nodes);
    (simulation.force('link') as d3.ForceLink<GraphNode, GraphLink>).links(links);
    simulation.alpha(0.5).restart();
}

defineExpose({ addSiteNode });

onResize(draw);
onMounted(draw);
watch(() => props.data, draw, { deep: true });

onBeforeUnmount(() => {
    simulation?.stop();
});
</script>

<template>
    <div ref="containerRef" class="h-full w-full">
        <Teleport to="body">
            <ChartTooltip :visible="tooltip.visible" :x="tooltip.x" :y="tooltip.y">
                <div class="flex flex-col gap-0.5">
                    <span class="font-medium">{{ tooltip.label }}</span>
                    <span class="tabular-nums text-muted-foreground">{{ tooltip.detail }}</span>
                </div>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
