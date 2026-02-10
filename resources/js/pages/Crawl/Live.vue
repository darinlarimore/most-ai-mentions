<script setup lang="ts">
import { Head, Link, InfiniteScroll, router } from '@inertiajs/vue3';
import GuestLayout from '@/layouts/GuestLayout.vue';
import type { Site } from '@/types';
import HypeScoreBadge from '@/components/HypeScoreBadge.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    Globe, Radio, ArrowLeft, Scan, Clock,
    Search, ImageIcon, Calculator, Camera, CheckCircle, Loader2, Sparkles, Tag,
} from 'lucide-vue-next';
import { ref, watch, onMounted, onUnmounted, computed, nextTick } from 'vue';

interface CrawlStep {
    key: string;
    label: string;
    icon: typeof Scan;
    message?: string;
    data?: Record<string, unknown>;
}

interface PaginatedSites {
    data: Site[];
    next_page_url: string | null;
}

const props = defineProps<{
    currentSite: Site | null;
    lastCrawledSite: Site | null;
    queuedSites: PaginatedSites;
}>();

const stepDefinitions: Record<string, { label: string; icon: typeof Scan }> = {
    fetching: { label: 'Fetching Homepage', icon: Globe },
    detecting_category: { label: 'Detecting Category', icon: Tag },
    detecting_mentions: { label: 'Detecting AI Mentions', icon: Search },
    detecting_images: { label: 'Scanning for AI Images', icon: ImageIcon },
    calculating_score: { label: 'Calculating Hype Score', icon: Calculator },
    generating_screenshot: { label: 'Generating Screenshot', icon: Camera },
    finishing: { label: 'Finishing Up', icon: Sparkles },
};

const allStepKeys = ['fetching', 'detecting_category', 'detecting_mentions', 'detecting_images', 'calculating_score', 'generating_screenshot', 'finishing'];

const initialSite = props.currentSite ?? props.lastCrawledSite;

const activeSite = ref<{ id: number; url: string; name: string | null; slug: string; screenshot_path?: string | null } | null>(
    initialSite ? { id: initialSite.id, url: initialSite.url, name: initialSite.name, slug: initialSite.slug, screenshot_path: initialSite.screenshot_path } : null,
);
const removedSiteIds = ref(new Set<number>());
const promotingSiteId = ref<number | null>(null);
const scanCardEntering = ref(false);
const filteredQueuedSites = computed(() =>
    (props.queuedSites?.data ?? []).filter(s => !removedSiteIds.value.has(s.id) && s.id !== activeSite.value?.id),
);
const completedSteps = ref<CrawlStep[]>(
    props.lastCrawledSite && !props.currentSite
        ? allStepKeys.map(key => ({ key, ...stepDefinitions[key] }))
        : [],
);
const currentStep = ref<CrawlStep | null>(null);
const completedResult = ref<{ hype_score: number; ai_mention_count: number } | null>(
    props.lastCrawledSite && !props.currentSite
        ? { hype_score: props.lastCrawledSite.hype_score, ai_mention_count: props.lastCrawledSite.latest_crawl_result?.ai_mention_count ?? 0 }
        : null,
);
const isLive = computed(() => activeSite.value && !completedResult.value);

const pendingSteps = computed(() => {
    const completedKeys = new Set(completedSteps.value.map(s => s.key));
    const currentKey = currentStep.value?.key;
    return allStepKeys
        .filter(key => key !== currentKey && !completedKeys.has(key))
        .map(key => ({ key, ...stepDefinitions[key] }));
});

let echoActivityChannel: ReturnType<typeof window.Echo.channel> | null = null;
let echoQueueChannel: ReturnType<typeof window.Echo.channel> | null = null;
let pollInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    // Subscribe to crawl activity events (progress, start, complete)
    echoActivityChannel = window.Echo.channel('crawl-activity');

    echoActivityChannel.listen('.CrawlStarted', (e: { site_id: number; site_url: string; site_name: string; site_slug: string }) => {
        // Check before filtering — is this site in the visible queue?
        const isInQueue = (props.queuedSites?.data ?? []).some(s => s.id === e.site_id);

        if (isInQueue) {
            // Start promote animation on the queue item (don't remove yet — let animation play)
            promotingSiteId.value = e.site_id;

            // After promote animation plays, remove from queue and show scan card
            setTimeout(() => {
                removedSiteIds.value.add(e.site_id);
                promotingSiteId.value = null;
                activeSite.value = { id: e.site_id, url: e.site_url, name: e.site_name, slug: e.site_slug };
                completedSteps.value = [];
                currentStep.value = null;
                completedResult.value = null;

                // Trigger scan card entrance animation
                scanCardEntering.value = true;
                nextTick(() => {
                    requestAnimationFrame(() => {
                        scanCardEntering.value = false;
                    });
                });
            }, 500);
        } else {
            // Site not in visible queue — remove and show scan card immediately
            removedSiteIds.value.add(e.site_id);
            activeSite.value = { id: e.site_id, url: e.site_url, name: e.site_name, slug: e.site_slug };
            completedSteps.value = [];
            currentStep.value = null;
            completedResult.value = null;

            scanCardEntering.value = true;
            nextTick(() => {
                requestAnimationFrame(() => {
                    scanCardEntering.value = false;
                });
            });
        }
    });

    echoActivityChannel.listen('.CrawlProgress', (e: { site_id: number; step: string; message: string; data: Record<string, unknown> }) => {
        // Move current step to completed
        if (currentStep.value) {
            completedSteps.value.push({ ...currentStep.value });
        }

        const def = stepDefinitions[e.step] ?? { label: e.step, icon: Scan };
        currentStep.value = {
            key: e.step,
            label: def.label,
            icon: def.icon,
            message: e.message,
            data: e.data,
        };
    });

    echoActivityChannel.listen('.CrawlCompleted', (e: { site_id: number; hype_score: number; ai_mention_count: number }) => {
        // Move current step to completed
        if (currentStep.value) {
            completedSteps.value.push({ ...currentStep.value });
            currentStep.value = null;
        }

        completedResult.value = { hype_score: e.hype_score, ai_mention_count: e.ai_mention_count };
    });

    // Subscribe to queue update events (replaces polling)
    echoQueueChannel = window.Echo.channel('crawl-queue');

    echoQueueChannel.listen('.QueueUpdated', () => {
        removedSiteIds.value.clear();
        router.reload({ only: ['queuedSites'], reset: ['queuedSites'] });
    });

    // Safety-net poll every 60 seconds (in case a WebSocket event is missed)
    pollInterval = setInterval(() => {
        removedSiteIds.value.clear();
        router.reload({ only: ['queuedSites'], reset: ['queuedSites'] });
    }, 60000);
});

onUnmounted(() => {
    if (echoActivityChannel) {
        window.Echo.leave('crawl-activity');
    }
    if (echoQueueChannel) {
        window.Echo.leave('crawl-queue');
    }
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>

<template>
    <Head title="Live Crawl - Most AI Mentions" />

    <GuestLayout>
        <div class="mx-auto max-w-4xl px-4 pb-12 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <Link href="/">
                    <Button variant="ghost" size="sm" class="mb-4">
                        <ArrowLeft class="size-4" />
                        Back to Leaderboard
                    </Button>
                </Link>

                <div class="flex items-center gap-3">
                    <Radio class="size-6 text-primary" />
                    <h1 class="text-3xl font-bold">Live Crawl</h1>
                    <span
                        v-if="isLive"
                        class="ml-2 inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                    >
                        <span class="relative flex size-2">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-75" />
                            <span class="relative inline-flex size-2 rounded-full bg-green-500" />
                        </span>
                        LIVE
                    </span>
                </div>
                <p class="mt-2 text-muted-foreground">
                    Watch our crawler analyze sites for AI hype in real-time.
                </p>
            </div>

            <!-- Active Crawl -->
            <Transition name="scan-card" :appear="false">
                <Card
                    v-if="activeSite"
                    :key="activeSite.id"
                    class="mb-8 overflow-hidden pt-0"
                    :class="{ 'scan-card-initial': scanCardEntering }"
                >
                    <!-- Scanning Animation Bar -->
                    <div v-if="!completedResult" class="h-1 overflow-hidden bg-muted">
                        <div class="animate-scan h-full w-1/3 bg-gradient-to-r from-transparent via-primary to-transparent" />
                    </div>
                    <!-- Completed bar -->
                    <div v-else class="h-1 bg-green-500" />

                    <CardHeader class="px-6 pt-5 pb-4">
                        <CardTitle class="flex items-center gap-2">
                            <Scan v-if="!completedResult" class="size-5 animate-pulse text-primary" />
                            <CheckCircle v-else class="size-5 text-green-500" />
                            {{ completedResult ? 'Scan Complete' : 'Currently Scanning' }}
                        </CardTitle>
                        <CardDescription>
                            {{ completedResult
                                ? `Finished analyzing ${activeSite.name || activeSite.url}`
                                : `Analyzing ${activeSite.name || activeSite.url} for AI mentions, animations, and visual effects`
                            }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-6 px-6 pb-6">
                        <!-- Site Info -->
                        <div class="flex items-center gap-4">
                            <div class="relative size-16 shrink-0 overflow-hidden rounded-xl border bg-muted">
                                <img
                                    v-if="activeSite.screenshot_path"
                                    :src="activeSite.screenshot_path"
                                    :alt="activeSite.name || activeSite.url"
                                    class="size-full object-cover"
                                />
                                <div v-else class="flex size-full items-center justify-center">
                                    <Globe class="size-8 text-muted-foreground" />
                                </div>
                                <div v-if="!completedResult" class="absolute inset-0 animate-pulse bg-primary/5" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <h3 class="text-xl font-semibold">
                                    {{ activeSite.name || activeSite.url }}
                                </h3>
                                <p class="text-sm text-muted-foreground">{{ activeSite.url }}</p>
                                <div v-if="completedResult" class="mt-1">
                                    <HypeScoreBadge :score="completedResult.hype_score" />
                                </div>
                            </div>
                        </div>

                        <!-- Progress Steps -->
                        <div class="flex flex-col gap-1.5">
                            <!-- Completed steps -->
                            <div
                                v-for="step in completedSteps"
                                :key="step.key"
                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm"
                            >
                                <CheckCircle class="size-4 shrink-0 text-green-500" />
                                <span class="font-medium text-foreground">{{ step.label }}</span>
                                <span v-if="step.data && step.data.ai_mention_count !== undefined" class="ml-auto text-xs text-muted-foreground">
                                    {{ step.data.ai_mention_count }} mentions found
                                </span>
                                <span v-else-if="step.data && step.data.ai_image_count !== undefined" class="ml-auto text-xs text-muted-foreground">
                                    {{ step.data.ai_image_count }} AI images
                                </span>
                                <span v-else-if="step.data && step.data.hype_score !== undefined" class="ml-auto text-xs text-muted-foreground">
                                    Score: {{ Math.round(step.data.hype_score as number) }}
                                </span>
                            </div>

                            <!-- Current step -->
                            <div
                                v-if="currentStep"
                                class="flex items-center gap-3 rounded-lg bg-primary/5 px-3 py-2 text-sm"
                            >
                                <Loader2 class="size-4 shrink-0 animate-spin text-primary" />
                                <span class="font-medium text-primary">{{ currentStep.label }}</span>
                                <span class="ml-auto text-xs text-muted-foreground">{{ currentStep.message }}</span>
                            </div>

                            <!-- Pending steps -->
                            <div
                                v-for="step in pendingSteps"
                                :key="step.key"
                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-muted-foreground/50"
                            >
                                <component :is="step.icon" class="size-4 shrink-0" />
                                <span>{{ step.label }}</span>
                            </div>
                        </div>

                        <!-- Completed result summary -->
                        <div v-if="completedResult" class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900/50 dark:bg-green-900/10">
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col gap-1">
                                    <span class="text-sm font-medium text-green-800 dark:text-green-300">Analysis Complete</span>
                                    <span class="text-xs text-green-600 dark:text-green-400">
                                        Found {{ completedResult.ai_mention_count }} AI mention{{ completedResult.ai_mention_count !== 1 ? 's' : '' }}
                                    </span>
                                </div>
                                <HypeScoreBadge :score="completedResult.hype_score" />
                            </div>
                        </div>

                        <div class="flex justify-center">
                            <Link :href="`/sites/${activeSite.slug}`">
                                <Button variant="outline">
                                    View Site Details
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </Transition>

            <!-- Queue -->
            <div>
                <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold">
                    <Clock class="size-5 text-muted-foreground" />
                    Waiting in Queue
                </h2>

                <InfiniteScroll data="queuedSites">
                    <TransitionGroup
                        name="queue-item"
                        tag="div"
                        class="relative flex flex-col gap-2"
                    >
                        <div
                            v-for="(site, index) in filteredQueuedSites"
                            :key="site.id"
                            class="flex items-center gap-4 rounded-xl border bg-card p-4 transition-all duration-300"
                            :class="{
                                'queue-item-promote': promotingSiteId === site.id,
                            }"
                        >
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground">
                                {{ index + 1 }}
                            </span>
                            <div class="relative size-10 shrink-0 overflow-hidden rounded-lg border bg-muted">
                                <img
                                    v-if="site.screenshot_path"
                                    :src="site.screenshot_path"
                                    :alt="site.name || site.domain"
                                    class="size-full object-cover"
                                />
                                <div v-else class="flex size-full items-center justify-center">
                                    <Globe class="size-4 text-muted-foreground" />
                                </div>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col">
                                <Link
                                    :href="`/sites/${site.slug}`"
                                    class="truncate text-sm font-medium transition-colors hover:text-primary"
                                >
                                    {{ site.name || site.domain }}
                                </Link>
                                <span class="truncate text-xs text-muted-foreground">{{ site.domain }}</span>
                            </div>
                            <HypeScoreBadge v-if="site.hype_score > 0" :score="site.hype_score" />
                            <span v-else class="text-xs text-muted-foreground">Pending</span>
                        </div>
                    </TransitionGroup>

                    <template #loading>
                        <div class="flex flex-col gap-2 pt-2">
                            <div
                                v-for="i in 3"
                                :key="i"
                                class="flex items-center gap-4 rounded-xl border bg-card p-4"
                            >
                                <div class="size-8 shrink-0 animate-pulse rounded-full bg-muted" />
                                <div class="size-10 shrink-0 animate-pulse rounded-lg bg-muted" />
                                <div class="flex flex-1 flex-col gap-2">
                                    <div class="h-4 w-40 animate-pulse rounded bg-muted" />
                                    <div class="h-3 w-28 animate-pulse rounded bg-muted" />
                                </div>
                            </div>
                        </div>
                    </template>
                </InfiniteScroll>

                <div v-if="filteredQueuedSites.length === 0 && !queuedSites?.next_page_url" class="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center">
                    <Clock class="size-12 text-muted-foreground" />
                    <h3 class="text-lg font-medium">No sites yet</h3>
                    <p class="text-muted-foreground">Submit a site to get the hype going!</p>
                    <Link href="/submit">
                        <Button>Submit a Site</Button>
                    </Link>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>

<style scoped>
@keyframes scan {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(400%);
    }
}

.animate-scan {
    animation: scan 2s ease-in-out infinite;
}

/* Queue item transitions — use transform/opacity only to stay on compositor */
.queue-item-move {
    transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1);
}

.queue-item-enter-active {
    transition: transform 0.5s cubic-bezier(0.25, 1, 0.5, 1), opacity 0.5s ease;
}

.queue-item-leave-active {
    transition: transform 0.5s cubic-bezier(0.25, 1, 0.5, 1), opacity 0.4s ease;
    position: absolute;
    width: 100%;
}

.queue-item-enter-from {
    opacity: 0;
    transform: translateX(-20px);
}

.queue-item-leave-to {
    opacity: 0;
    transform: translateX(20px);
}

/* Queue item "promote" — smooth slide-left exit */
.queue-item-promote {
    transition: transform 0.5s cubic-bezier(0.25, 1, 0.5, 1), opacity 0.5s ease;
    transform: translateX(-40px);
    opacity: 0;
}

/* Active scan card entrance */
.scan-card-enter-active {
    transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1), opacity 0.4s ease;
}

.scan-card-enter-from,
.scan-card-initial {
    opacity: 0;
    transform: translateY(16px);
}

.scan-card-leave-active {
    transition: opacity 0.3s ease;
}

.scan-card-leave-to {
    opacity: 0;
}
</style>
