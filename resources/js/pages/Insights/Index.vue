<script setup lang="ts">
import { Deferred, WhenVisible } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    ChartPie,
    ChartScatter,
    Cloud,
    GitBranch,
    Globe,
    Hexagon,
    Layers,
    LayoutGrid,
} from 'lucide-vue-next';
import { reactive, ref, onMounted, onUnmounted, nextTick, watch } from 'vue';
import D3DonutChart from '@/components/charts/D3DonutChart.vue';
import D3ForceGraph from '@/components/charts/D3ForceGraph.vue';
import type { NetworkData } from '@/components/charts/D3ForceGraph.vue';
import D3Hexbin from '@/components/charts/D3Hexbin.vue';
import D3HorizontalBar from '@/components/charts/D3HorizontalBar.vue';
import D3RadialTree from '@/components/charts/D3RadialTree.vue';
import D3RealtimeHorizon from '@/components/charts/D3RealtimeHorizon.vue';
import D3ScatterPlot from '@/components/charts/D3ScatterPlot.vue';
import D3StackedBar from '@/components/charts/D3StackedBar.vue';
import D3Treemap from '@/components/charts/D3Treemap.vue';
import D3VerticalBar from '@/components/charts/D3VerticalBar.vue';
import D3WordCloud from '@/components/charts/D3WordCloud.vue';
import D3WorldMap from '@/components/charts/D3WorldMap.vue';
import TickerNumber from '@/components/TickerNumber.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import GuestLayout from '@/layouts/GuestLayout.vue';

interface TermFrequencyItem {
    term: string;
    count: number;
}

interface TechStackItem {
    tech: string;
    count: number;
}

interface ScoreDistItem {
    range: string;
    count: number;
}

interface ScatterItem {
    domain: string;
    slug: string;
    mentions: number;
    score: number;
}

interface HostingMapItem {
    domain: string;
    slug: string;
    latitude: number;
    longitude: number;
    hype_score: number;
}

interface CrawlerSpeedItem {
    timestamp: string;
    duration_ms: number;
    has_error?: boolean;
}

interface LabelValue {
    label: string;
    value: number;
}

interface CrawlErrorsData {
    by_category: LabelValue[];
    over_time: Record<string, string | number>[];
    top_domains: LabelValue[];
}

defineOptions({ layout: GuestLayout });

const props = defineProps<{
    pipelineStats: {
        total_sites: number;
        crawled_sites: number;
        queued_sites: number;
        total_crawls: number;
    };
    termFrequency: TermFrequencyItem[];
    techStackDistribution: TechStackItem[];
    scoreDistribution: ScoreDistItem[];
    mentionsVsScore: ScatterItem[];
    hostingMap: HostingMapItem[];
    crawlerSpeed: CrawlerSpeedItem[];
    crawlErrors: CrawlErrorsData;
}>();

const termView = ref<'bar' | 'treemap'>('treemap');
const techView = ref<'bar' | 'radial' | 'donut' | 'cloud'>('cloud');
const scoreView = ref<'bar' | 'donut'>('bar');
const scatterView = ref<'scatter' | 'hexbin'>('scatter');
const errorView = ref<'donut' | 'bar' | 'timeline' | 'domains'>('donut');

const worldMapRef = ref<InstanceType<typeof D3WorldMap> | null>(null);
const forceGraphRef = ref<InstanceType<typeof D3ForceGraph> | null>(null);
const networkData = ref<NetworkData | null>(null);
const networkLoading = ref(true);
const networkCardRef = ref<InstanceType<typeof Card> | null>(null);
let networkObserver: IntersectionObserver | null = null;

const liveStats = reactive({ ...props.pipelineStats });

// Local reactive refs for chart data — populated by WhenVisible on first load,
// then updated via JSON fetch to avoid full component remounts.
const liveTermFrequency = ref<TermFrequencyItem[]>(props.termFrequency ?? []);
const liveTechStack = ref<TechStackItem[]>(props.techStackDistribution ?? []);
const liveScoreDist = ref<ScoreDistItem[]>(props.scoreDistribution ?? []);
const liveMentionsVsScore = ref<ScatterItem[]>(props.mentionsVsScore ?? []);
const liveCrawlErrors = ref<CrawlErrorsData | null>(props.crawlErrors ?? null);

// Sync from WhenVisible's initial lazy load into local refs
watch(() => props.termFrequency, (v) => { if (v?.length) liveTermFrequency.value = v; });
watch(() => props.techStackDistribution, (v) => { if (v?.length) liveTechStack.value = v; });
watch(() => props.scoreDistribution, (v) => { if (v?.length) liveScoreDist.value = v; });
watch(() => props.mentionsVsScore, (v) => { if (v?.length) liveMentionsVsScore.value = v; });
watch(() => props.crawlErrors, (v) => { if (v) liveCrawlErrors.value = v; });

const stats = [
    { label: 'Total Sites', key: 'total_sites' as const },
    { label: 'Crawled', key: 'crawled_sites' as const },
    { label: 'In Queue', key: 'queued_sites' as const },
    { label: 'Total Crawls', key: 'total_crawls' as const },
];

async function refreshStats() {
    try {
        const res = await fetch('/insights/stats');
        if (!res.ok) return;
        const data = await res.json();
        Object.assign(liveStats, data);
    } catch {
        // silently ignore
    }
}

async function loadNetworkData() {
    try {
        const res = await fetch('/insights/network');
        if (!res.ok) return;
        networkData.value = await res.json();
    } catch {
        // silently ignore
    } finally {
        networkLoading.value = false;
    }
}

let chartRefreshTimeout: ReturnType<typeof setTimeout> | null = null;

async function refreshCharts() {
    try {
        const res = await fetch('/insights/charts');
        if (!res.ok) return;
        const data = await res.json();
        if (data.termFrequency) liveTermFrequency.value = data.termFrequency;
        if (data.techStackDistribution) liveTechStack.value = data.techStackDistribution;
        if (data.scoreDistribution) liveScoreDist.value = data.scoreDistribution;
        if (data.mentionsVsScore) liveMentionsVsScore.value = data.mentionsVsScore;
        if (data.crawlErrors) liveCrawlErrors.value = data.crawlErrors;
    } catch {
        // silently ignore
    }
}

function scheduleChartRefresh() {
    if (chartRefreshTimeout) clearTimeout(chartRefreshTimeout);
    chartRefreshTimeout = setTimeout(() => {
        chartRefreshTimeout = null;
        refreshCharts();
    }, 5000);
}

let activityChannel: ReturnType<typeof window.Echo.channel> | null = null;
let queueChannel: ReturnType<typeof window.Echo.channel> | null = null;

onMounted(() => {
    activityChannel = window.Echo.channel('crawl-activity');
    activityChannel.listen('.CrawlCompleted', (e: Record<string, unknown>) => {
        refreshStats();
        forceGraphRef.value?.addSiteNode(e as Parameters<InstanceType<typeof D3ForceGraph>['addSiteNode']>[0]);
        if (e.latitude && e.longitude) {
            worldMapRef.value?.addPoint({
                domain: e.domain as string,
                slug: e.slug as string,
                latitude: e.latitude as number,
                longitude: e.longitude as number,
                hypeScore: (e.hype_score as number) ?? 0,
            });
        }
        scheduleChartRefresh();
    });

    queueChannel = window.Echo.channel('crawl-queue');
    queueChannel.listen('.QueueUpdated', refreshStats);

    nextTick(() => {
        const el = networkCardRef.value?.$el as HTMLElement | undefined;
        if (el) {
            networkObserver = new IntersectionObserver(
                ([entry]) => {
                    if (entry.isIntersecting) {
                        loadNetworkData();
                        networkObserver?.disconnect();
                        networkObserver = null;
                    }
                },
                { rootMargin: '300px' },
            );
            networkObserver.observe(el);
        }
    });
});

onUnmounted(() => {
    if (activityChannel) {
        window.Echo.leave('crawl-activity');
    }
    if (queueChannel) {
        window.Echo.leave('crawl-queue');
    }
    if (chartRefreshTimeout) {
        clearTimeout(chartRefreshTimeout);
    }
    networkObserver?.disconnect();
});
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight">Insights</h1>
            <p class="mt-2 text-muted-foreground">Aggregate analytics across all crawled sites</p>
        </div>

        <!-- Pipeline Stats -->
        <div class="mb-8 grid grid-cols-2 divide-x divide-border rounded-xl border bg-card sm:grid-cols-4 [&>*:nth-child(n+3)]:border-t [&>*:nth-child(n+3)]:sm:border-t-0">
            <div v-for="stat in stats" :key="stat.key" class="flex flex-col items-center justify-center px-2 py-4 text-center">
                <span class="text-4xl font-extrabold tracking-tight sm:text-5xl">
                    <TickerNumber :value="liveStats[stat.key]" />
                </span>
                <span class="mt-1 text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ stat.label }}</span>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Server Hosting Map -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Server Hosting Map</CardTitle>
                    <CardDescription>Geographic distribution of where crawled sites are hosted</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="hostingMap">
                        <template #fallback>
                            <Skeleton class="h-[28rem] w-full" />
                        </template>
                        <div class="h-[28rem]">
                            <D3WorldMap
                                ref="worldMapRef"
                                :data="
                                    (hostingMap ?? []).map((s) => ({
                                        domain: s.domain,
                                        slug: s.slug,
                                        latitude: s.latitude,
                                        longitude: s.longitude,
                                        hypeScore: s.hype_score,
                                    }))
                                "
                            />
                        </div>
                    </Deferred>
                </CardContent>
            </Card>

            <!-- AI Term Frequency -->
            <Card class="lg:col-span-2">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>AI Term Frequency</CardTitle>
                        <CardDescription>Most commonly detected AI terms across crawled sites</CardDescription>
                    </div>
                    <div class="flex gap-1 rounded-lg border p-0.5">
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="termView === 'treemap' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="termView = 'treemap'"
                        >
                            <LayoutGrid class="size-4" />
                        </button>
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="termView === 'bar' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="termView = 'bar'"
                        >
                            <BarChart3 class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <WhenVisible data="termFrequency" :buffer="300">
                        <template #fallback>
                            <Skeleton class="h-96 w-full rounded-lg" />
                        </template>
                        <div v-if="termView === 'bar'" :style="{ height: Math.max(300, liveTermFrequency.length * 28) + 'px' }">
                            <D3HorizontalBar
                                :data="liveTermFrequency.map((t) => ({ label: t.term, value: t.count }))"
                            />
                        </div>
                        <div v-else class="h-96">
                            <D3Treemap
                                :data="liveTermFrequency.map((t) => ({ label: t.term, value: t.count }))"
                            />
                        </div>
                    </WhenVisible>
                </CardContent>
            </Card>

            <!-- Tech Stack Distribution -->
            <Card class="lg:col-span-2">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Tech Stack Distribution</CardTitle>
                        <CardDescription>Technologies detected across crawled sites</CardDescription>
                    </div>
                    <div class="flex gap-1 rounded-lg border p-0.5">
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="techView === 'cloud' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="techView = 'cloud'"
                        >
                            <Cloud class="size-4" />
                        </button>
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="techView === 'bar' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="techView = 'bar'"
                        >
                            <BarChart3 class="size-4" />
                        </button>
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="techView === 'donut' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="techView = 'donut'"
                        >
                            <ChartPie class="size-4" />
                        </button>
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="techView === 'radial' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="techView = 'radial'"
                        >
                            <GitBranch class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <WhenVisible data="techStackDistribution" :buffer="300">
                        <template #fallback>
                            <Skeleton class="h-96 w-full rounded-lg" />
                        </template>
                        <template v-if="liveTechStack.length">
                            <div
                                v-if="techView === 'bar'"
                                :style="{ height: Math.max(300, liveTechStack.length * 28) + 'px' }"
                            >
                                <D3HorizontalBar
                                    :data="liveTechStack.map((t) => ({ label: t.tech, value: t.count }))"
                                    color="var(--chart-2)"
                                />
                            </div>
                            <div v-else-if="techView === 'donut'" class="h-96">
                                <D3DonutChart
                                    :data="liveTechStack.map((t) => ({ label: t.tech, value: t.count }))"
                                />
                            </div>
                            <div v-else-if="techView === 'cloud'" class="h-96">
                                <D3WordCloud
                                    :data="liveTechStack.map((t) => ({ label: t.tech, value: t.count }))"
                                />
                            </div>
                            <div v-else class="h-[500px]">
                                <D3RadialTree
                                    :data="liveTechStack.map((t) => ({ label: t.tech, value: t.count }))"
                                />
                            </div>
                        </template>
                        <div v-else class="flex h-48 items-center justify-center text-muted-foreground">
                            No tech stack data yet. Data populates after the next crawl cycle.
                        </div>
                    </WhenVisible>
                </CardContent>
            </Card>

            <!-- Score Distribution Histogram -->
            <Card class="lg:col-span-2">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Score Distribution</CardTitle>
                        <CardDescription>Hype score ranges across all sites</CardDescription>
                    </div>
                    <div class="flex gap-1 rounded-lg border p-0.5">
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="scoreView === 'bar' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="scoreView = 'bar'"
                        >
                            <BarChart3 class="size-4" />
                        </button>
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="scoreView === 'donut' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="scoreView = 'donut'"
                        >
                            <ChartPie class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <WhenVisible data="scoreDistribution" :buffer="300">
                        <template #fallback>
                            <Skeleton class="h-64 w-full" />
                        </template>
                        <div v-if="scoreView === 'bar'" class="h-64">
                            <D3VerticalBar
                                :data="liveScoreDist.map((s) => ({ label: s.range, value: s.count }))"
                            />
                        </div>
                        <div v-else class="h-64">
                            <D3DonutChart
                                :data="liveScoreDist.map((s) => ({ label: s.range, value: s.count }))"
                            />
                        </div>
                    </WhenVisible>
                </CardContent>
            </Card>

            <!-- Mentions vs Score Scatter -->
            <Card class="lg:col-span-2">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Mentions vs Score</CardTitle>
                        <CardDescription>Relationship between AI mention count and hype score</CardDescription>
                    </div>
                    <div class="flex gap-1 rounded-lg border p-0.5">
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="scatterView === 'scatter' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="scatterView = 'scatter'"
                        >
                            <ChartScatter class="size-4" />
                        </button>
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="scatterView === 'hexbin' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="scatterView = 'hexbin'"
                        >
                            <Hexagon class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <WhenVisible data="mentionsVsScore" :buffer="300">
                        <template #fallback>
                            <Skeleton class="h-80 w-full" />
                        </template>
                        <div v-if="scatterView === 'scatter'" class="h-80">
                            <D3ScatterPlot
                                :data="
                                    liveMentionsVsScore.map((s) => ({
                                        label: s.domain,
                                        x: s.mentions,
                                        y: s.score,
                                        slug: s.slug,
                                    }))
                                "
                                x-label="AI Mentions"
                                y-label="Hype Score"
                            />
                        </div>
                        <div v-else-if="scatterView === 'hexbin'" class="h-80">
                            <D3Hexbin
                                :data="
                                    liveMentionsVsScore.map((s) => ({
                                        label: s.domain,
                                        x: s.mentions,
                                        y: s.score,
                                        slug: s.slug,
                                    }))
                                "
                                x-label="AI Mentions"
                                y-label="Hype Score"
                            />
                        </div>
                    </WhenVisible>
                </CardContent>
            </Card>

            <!-- Crawl Errors -->
            <Card class="lg:col-span-2">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Crawl Errors</CardTitle>
                        <CardDescription>Error patterns and failing domains from crawl pipeline</CardDescription>
                    </div>
                    <div class="flex gap-1 rounded-lg border p-0.5">
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="errorView === 'donut' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="errorView = 'donut'"
                        >
                            <ChartPie class="size-4" />
                        </button>
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="errorView === 'bar' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="errorView = 'bar'"
                        >
                            <BarChart3 class="size-4" />
                        </button>
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="errorView === 'timeline' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="errorView = 'timeline'"
                        >
                            <Layers class="size-4" />
                        </button>
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="errorView === 'domains' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="errorView = 'domains'"
                        >
                            <Globe class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <WhenVisible data="crawlErrors" :buffer="300">
                        <template #fallback>
                            <Skeleton class="h-72 w-full rounded-lg" />
                        </template>
                        <template v-if="liveCrawlErrors?.by_category?.length || liveCrawlErrors?.over_time?.length || liveCrawlErrors?.top_domains?.length">
                            <div v-if="errorView === 'donut'" class="h-72">
                                <D3DonutChart :data="liveCrawlErrors.by_category ?? []" />
                            </div>
                            <div
                                v-else-if="errorView === 'bar'"
                                :style="{ height: Math.max(200, (liveCrawlErrors.by_category?.length ?? 0) * 28) + 'px' }"
                            >
                                <D3HorizontalBar :data="liveCrawlErrors.by_category ?? []" color="var(--chart-4)" />
                            </div>
                            <div v-else-if="errorView === 'timeline'" class="h-72">
                                <D3StackedBar :data="(liveCrawlErrors.over_time ?? []) as any" />
                            </div>
                            <div v-else-if="errorView === 'domains'" class="h-96">
                                <D3Treemap :data="liveCrawlErrors.top_domains ?? []" />
                            </div>
                        </template>
                        <div v-else class="flex h-48 items-center justify-center text-muted-foreground">
                            <div class="text-center">
                                <AlertTriangle class="mx-auto mb-2 size-8 opacity-50" />
                                <p>No crawl errors recorded yet.</p>
                            </div>
                        </div>
                    </WhenVisible>
                </CardContent>
            </Card>

            <!-- Crawl Duration -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Crawl Duration</CardTitle>
                    <CardDescription>Individual crawl durations in real-time</CardDescription>
                </CardHeader>
                <CardContent>
                    <WhenVisible data="crawlerSpeed" :buffer="300">
                        <template #fallback>
                            <Skeleton class="h-40 w-full" />
                        </template>
                        <div v-if="crawlerSpeed?.length" class="h-40">
                            <D3RealtimeHorizon :initial-data="crawlerSpeed ?? []" />
                        </div>
                        <div v-else class="flex h-40 items-center justify-center text-muted-foreground">
                            No crawl duration data yet. Data populates after sites are crawled.
                        </div>
                    </WhenVisible>
                </CardContent>
            </Card>

            <!-- Sites ↔ AI Terms Network -->
            <Card ref="networkCardRef" class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Sites &amp; AI Terms Network</CardTitle>
                    <CardDescription>Force-directed graph of sites linked to the AI terms they mention</CardDescription>
                </CardHeader>
                <CardContent>
                    <Skeleton v-if="networkLoading" class="h-[32rem] w-full" />
                    <div v-else-if="networkData?.nodes?.length" class="h-[32rem]">
                        <D3ForceGraph ref="forceGraphRef" :data="networkData" />
                    </div>
                    <div v-else class="flex h-48 items-center justify-center text-muted-foreground">
                        No network data yet. Data populates after sites are crawled.
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
