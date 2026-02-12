<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Loader2, Clock } from 'lucide-vue-next';
import { useSubmittedCrawls } from '@/composables/useSubmittedCrawls';

const { nearestCrawl, hasActiveCrawls } = useSubmittedCrawls();

function domainFromUrl(url: string): string {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="-translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-full opacity-0"
    >
        <div
            v-if="hasActiveCrawls && nearestCrawl"
            class="border-b bg-muted/50 backdrop-blur"
        >
            <div class="mx-auto flex h-8 max-w-7xl items-center gap-2 px-4 text-xs sm:px-6 lg:px-8">
                <template v-if="nearestCrawl.status === 'crawling'">
                    <Loader2 class="size-3 shrink-0 animate-spin text-primary" />
                    <span class="truncate text-muted-foreground">
                        Scanning
                        <Link :href="`/sites/${nearestCrawl.slug}`" class="font-medium text-foreground hover:underline">
                            {{ domainFromUrl(nearestCrawl.url) }}
                        </Link>
                        <template v-if="nearestCrawl.step">
                            <span class="mx-1 text-muted-foreground/50">&mdash;</span>
                            {{ nearestCrawl.step }}
                        </template>
                    </span>
                </template>
                <template v-else>
                    <Clock class="size-3 shrink-0 text-muted-foreground" />
                    <span class="truncate text-muted-foreground">
                        <Link :href="`/sites/${nearestCrawl.slug}`" class="font-medium text-foreground hover:underline">
                            {{ domainFromUrl(nearestCrawl.url) }}
                        </Link>
                        <template v-if="nearestCrawl.queuePosition">
                            is #{{ nearestCrawl.queuePosition }} of {{ nearestCrawl.queueTotal }} in queue
                        </template>
                        <template v-else>
                            is queued for crawling...
                        </template>
                    </span>
                </template>
            </div>
        </div>
    </Transition>
</template>
