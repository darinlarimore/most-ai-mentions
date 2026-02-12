<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Loader2, CheckCircle, XCircle, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import HypeScoreBadge from '@/components/HypeScoreBadge.vue';
import { useSubmittedCrawls } from '@/composables/useSubmittedCrawls';
import type { SubmittedCrawl } from '@/composables/useSubmittedCrawls';

const { crawls, removeCrawl } = useSubmittedCrawls();

const fadingOut = ref(new Set<number>());

function domainFromUrl(url: string): string {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}

function dismiss(siteId: number): void {
    fadingOut.value.add(siteId);
    setTimeout(() => {
        removeCrawl(siteId);
        fadingOut.value.delete(siteId);
    }, 300);
}

// Auto-fade completed crawls after 10 seconds
const autoFadeTimers = new Map<number, ReturnType<typeof setTimeout>>();

watch(
    crawls,
    (current) => {
        for (const crawl of current) {
            if ((crawl.status === 'completed' || crawl.status === 'failed') && !autoFadeTimers.has(crawl.siteId)) {
                autoFadeTimers.set(
                    crawl.siteId,
                    setTimeout(() => {
                        dismiss(crawl.siteId);
                        autoFadeTimers.delete(crawl.siteId);
                    }, 10000),
                );
            }
        }
    },
    { deep: true, immediate: true },
);

function statusIcon(status: SubmittedCrawl['status']) {
    switch (status) {
        case 'queued':
            return Clock;
        case 'crawling':
            return Loader2;
        case 'completed':
            return CheckCircle;
        case 'failed':
            return XCircle;
    }
}
</script>

<template>
    <div v-if="crawls.length > 0" class="mt-6 w-full">
        <h3 class="mb-3 text-sm font-medium text-muted-foreground">Your Submitted Sites</h3>

        <div class="divide-y rounded-lg border">
            <div
                v-for="crawl in crawls"
                :key="crawl.siteId"
                :class="[
                    'flex items-center gap-3 px-4 py-3 transition-opacity duration-300',
                    fadingOut.has(crawl.siteId) ? 'opacity-0' : 'opacity-100',
                ]"
            >
                <!-- Status Icon -->
                <component
                    :is="statusIcon(crawl.status)"
                    :class="[
                        'size-4 shrink-0',
                        crawl.status === 'queued' && 'text-muted-foreground',
                        crawl.status === 'crawling' && 'animate-spin text-primary',
                        crawl.status === 'completed' && 'text-green-500',
                        crawl.status === 'failed' && 'text-destructive',
                    ]"
                />

                <!-- Site Info -->
                <div class="flex min-w-0 flex-1 flex-col">
                    <Link
                        :href="`/sites/${crawl.slug}`"
                        class="truncate text-sm font-medium transition-colors hover:text-primary"
                    >
                        {{ domainFromUrl(crawl.url) }}
                    </Link>
                    <span v-if="crawl.status === 'crawling' && crawl.step" class="truncate text-xs text-muted-foreground">
                        {{ crawl.step }}
                    </span>
                    <span v-else-if="crawl.status === 'queued'" class="text-xs text-muted-foreground">
                        <template v-if="crawl.queuePosition">
                            #{{ crawl.queuePosition }} of {{ crawl.queueTotal }} in queue
                        </template>
                        <template v-else>
                            Queued for crawling
                        </template>
                    </span>
                    <span v-else-if="crawl.status === 'failed'" class="text-xs text-destructive">
                        Crawl failed
                    </span>
                </div>

                <!-- Score / Dismiss -->
                <HypeScoreBadge v-if="crawl.status === 'completed' && crawl.hypeScore" :score="crawl.hypeScore" />

                <button
                    v-if="crawl.status === 'completed' || crawl.status === 'failed'"
                    type="button"
                    class="shrink-0 rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground"
                    @click="dismiss(crawl.siteId)"
                >
                    <X class="size-3.5" />
                </button>
            </div>
        </div>
    </div>
</template>
