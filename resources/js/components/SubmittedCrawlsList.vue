<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Loader2, CheckCircle, XCircle, X, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import HypeScoreBadge from '@/components/HypeScoreBadge.vue';
import { useSubmittedCrawls } from '@/composables/useSubmittedCrawls';
import type { SubmittedCrawl } from '@/composables/useSubmittedCrawls';

const { crawls, removeCrawl, clearCompleted } = useSubmittedCrawls();

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
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-muted-foreground">Your Submitted Sites</h3>
            <button
                type="button"
                class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
                @click="clearCompleted"
            >
                <Trash2 class="size-3" />
                Clear
            </button>
        </div>

        <div class="rounded-lg border divide-y">
            <TransitionGroup
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-300 ease-in"
                leave-from-class="opacity-100 max-h-20"
                leave-to-class="opacity-0 max-h-0"
            >
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
                            Queued for crawling
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
                        class="shrink-0 rounded p-0.5 text-muted-foreground hover:text-foreground transition-colors"
                        @click="dismiss(crawl.siteId)"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </div>
</template>
