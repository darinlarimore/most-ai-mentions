<script setup lang="ts">
import { reactive, ref, onMounted, onUnmounted } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { BarChart3, LayoutGrid, GitBranch } from 'lucide-vue-next';
import D3HorizontalBar from '@/components/charts/D3HorizontalBar.vue';
import D3Treemap from '@/components/charts/D3Treemap.vue';
import D3RadialTree from '@/components/charts/D3RadialTree.vue';
import D3DonutChart from '@/components/charts/D3DonutChart.vue';
import D3VerticalBar from '@/components/charts/D3VerticalBar.vue';
import D3ScatterPlot from '@/components/charts/D3ScatterPlot.vue';
import D3WorldMap from '@/components/charts/D3WorldMap.vue';
import D3HorizonChart from '@/components/charts/D3HorizonChart.vue';
import TickerNumber from '@/components/TickerNumber.vue';

interface TermFrequencyItem {
    term: string;
    count: number;
}

interface TechStackItem {
    tech: string;
    count: number;
}

interface ServerItem {
    server: string;
    count: number;
}

interface CategoryItem {
    category: string;
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

interface ScoreTimelineItem {
    date: string;
    value: number;
}

defineOptions({ layout: GuestLayout });

const props = defineProps<{
    pipelineStats: {
        total_sites: number;
        crawled_sites: number;
        pending_sites: number;
        total_crawls: number;
    };
    termFrequency: TermFrequencyItem[];
    techStackDistribution: TechStackItem[];
    serverDistribution: ServerItem[];
    categoryBreakdown: CategoryItem[];
    scoreDistribution: ScoreDistItem[];
    mentionsVsScore: ScatterItem[];
    hostingMap: HostingMapItem[];
    scoreTimeline: ScoreTimelineItem[];
}>();

const termView = ref<'bar' | 'treemap'>('bar');
const techView = ref<'bar' | 'radial'>('bar');

const liveStats = reactive({ ...props.pipelineStats });

const stats = [
    { label: 'Total Sites', key: 'total_sites' as const },
    { label: 'Crawled', key: 'crawled_sites' as const },
    { label: 'Pending', key: 'pending_sites' as const },
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

let echoChannel: ReturnType<typeof window.Echo.channel> | null = null;

onMounted(() => {
    echoChannel = window.Echo.channel('crawl-activity');
    echoChannel.listen('.CrawlCompleted', refreshStats);
});

onUnmounted(() => {
    if (echoChannel) {
        window.Echo.leave('crawl-activity');
    }
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
        <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-4">
            <Card v-for="stat in stats" :key="stat.key">
                <CardContent class="pt-6">
                    <p class="text-sm font-medium text-muted-foreground">{{ stat.label }}</p>
                    <p class="text-3xl font-bold">
                        <TickerNumber :value="liveStats[stat.key]" />
                    </p>
                </CardContent>
            </Card>
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
                        <div v-if="hostingMap?.length" class="h-[28rem]">
                            <D3WorldMap
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
                        <div v-else class="flex h-48 items-center justify-center text-muted-foreground">
                            No geocoded server data yet. Coordinates populate during crawl.
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
                            :class="termView === 'bar' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="termView = 'bar'"
                        >
                            <BarChart3 class="size-4" />
                        </button>
                        <button
                            class="rounded-md p-1.5 transition-colors"
                            :class="termView === 'treemap' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="termView = 'treemap'"
                        >
                            <LayoutGrid class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <Deferred data="termFrequency">
                        <template #fallback>
                            <div class="space-y-2">
                                <Skeleton v-for="i in 10" :key="i" class="h-6 w-full" />
                            </div>
                        </template>
                        <div v-if="termView === 'bar'" :style="{ height: Math.max(300, (termFrequency?.length ?? 0) * 28) + 'px' }">
                            <D3HorizontalBar
                                :data="(termFrequency ?? []).map((t) => ({ label: t.term, value: t.count }))"
                            />
                        </div>
                        <div v-else class="h-96">
                            <D3Treemap
                                :data="(termFrequency ?? []).map((t) => ({ label: t.term, value: t.count }))"
                            />
                        </div>
                    </Deferred>
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
                            :class="techView === 'bar' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="techView = 'bar'"
                        >
                            <BarChart3 class="size-4" />
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
                    <Deferred data="techStackDistribution">
                        <template #fallback>
                            <div class="space-y-2">
                                <Skeleton v-for="i in 8" :key="i" class="h-6 w-full" />
                            </div>
                        </template>
                        <template v-if="techStackDistribution?.length">
                            <div
                                v-if="techView === 'bar'"
                                :style="{ height: Math.max(300, (techStackDistribution?.length ?? 0) * 28) + 'px' }"
                            >
                                <D3HorizontalBar
                                    :data="(techStackDistribution ?? []).map((t) => ({ label: t.tech, value: t.count }))"
                                    color="var(--chart-2)"
                                />
                            </div>
                            <div v-else class="h-[500px]">
                                <D3RadialTree
                                    :data="(techStackDistribution ?? []).map((t) => ({ label: t.tech, value: t.count }))"
                                />
                            </div>
                        </template>
                        <div v-else class="flex h-48 items-center justify-center text-muted-foreground">
                            No tech stack data yet. Data populates after the next crawl cycle.
                        </div>
                    </Deferred>
                </CardContent>
            </Card>

            <!-- Server Software -->
            <Card>
                <CardHeader>
                    <CardTitle>Server Software</CardTitle>
                    <CardDescription>Distribution of web servers</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="serverDistribution">
                        <template #fallback>
                            <Skeleton class="mx-auto h-64 w-64 rounded-full" />
                        </template>
                        <div v-if="serverDistribution?.length" class="h-64">
                            <D3DonutChart
                                :data="(serverDistribution ?? []).map((s) => ({ label: s.server, value: s.count }))"
                            />
                        </div>
                        <div v-else class="flex h-48 items-center justify-center text-muted-foreground">
                            No server data yet.
                        </div>
                    </Deferred>
                </CardContent>
            </Card>

            <!-- Category Distribution -->
            <Card>
                <CardHeader>
                    <CardTitle>Site Categories</CardTitle>
                    <CardDescription>Distribution by detected category</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="categoryBreakdown">
                        <template #fallback>
                            <Skeleton class="mx-auto h-64 w-64 rounded-full" />
                        </template>
                        <div v-if="categoryBreakdown?.length" class="h-64">
                            <D3DonutChart
                                :data="(categoryBreakdown ?? []).map((c) => ({ label: c.category, value: c.count }))"
                            />
                        </div>
                        <div v-else class="flex h-48 items-center justify-center text-muted-foreground">
                            No category data yet.
                        </div>
                    </Deferred>
                </CardContent>
            </Card>

            <!-- Score Distribution Histogram -->
            <Card>
                <CardHeader>
                    <CardTitle>Score Distribution</CardTitle>
                    <CardDescription>Hype score ranges across all sites</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="scoreDistribution">
                        <template #fallback>
                            <Skeleton class="h-64 w-full" />
                        </template>
                        <div class="h-64">
                            <D3VerticalBar
                                :data="(scoreDistribution ?? []).map((s) => ({ label: s.range, value: s.count }))"
                            />
                        </div>
                    </Deferred>
                </CardContent>
            </Card>

            <!-- Mentions vs Score Scatter -->
            <Card>
                <CardHeader>
                    <CardTitle>Mentions vs Score</CardTitle>
                    <CardDescription>Relationship between AI mention count and hype score</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="mentionsVsScore">
                        <template #fallback>
                            <Skeleton class="h-64 w-full" />
                        </template>
                        <div class="h-64">
                            <D3ScatterPlot
                                :data="
                                    (mentionsVsScore ?? []).map((s) => ({
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
                    </Deferred>
                </CardContent>
            </Card>

            <!-- Score Timeline -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Score Timeline</CardTitle>
                    <CardDescription>Average hype score trend over the last 60 days</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="scoreTimeline">
                        <template #fallback>
                            <Skeleton class="h-40 w-full" />
                        </template>
                        <div v-if="scoreTimeline?.length" class="h-40">
                            <D3HorizonChart :data="scoreTimeline ?? []" label="Avg Hype Score" />
                        </div>
                        <div v-else class="flex h-40 items-center justify-center text-muted-foreground">
                            No score history yet. Data populates after daily snapshots.
                        </div>
                    </Deferred>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
