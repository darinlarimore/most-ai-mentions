<script setup lang="ts">
import { Head, Link, Deferred, usePoll } from '@inertiajs/vue3';
import GuestLayout from '@/layouts/GuestLayout.vue';
import type { Site } from '@/types';
import HypeScoreBadge from '@/components/HypeScoreBadge.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Globe, Radio, Clock, ArrowLeft, Loader2 } from 'lucide-vue-next';

defineProps<{
    currentlyCrawling: Site | null;
    queuedSites: Site[];
}>();

usePoll(5000);
</script>

<template>
    <Head title="Crawl Queue - Most AI Mentions" />

    <GuestLayout>
        <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
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
                    <h1 class="text-3xl font-bold">Crawl Queue</h1>
                </div>
                <p class="mt-2 text-muted-foreground">
                    Sites waiting to be analyzed for AI hype content.
                </p>
            </div>

            <!-- Currently Crawling -->
            <Card class="mb-8 border-primary/30">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <span class="relative flex size-3">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-75" />
                            <span class="relative inline-flex size-3 rounded-full bg-green-500" />
                        </span>
                        Currently Scanning
                    </CardTitle>
                    <CardDescription>
                        Our crawler is analyzing this site right now
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="currentlyCrawling" class="flex items-center gap-4">
                        <div class="relative size-14 shrink-0 overflow-hidden rounded-lg border bg-muted">
                            <img
                                v-if="currentlyCrawling.screenshot_path"
                                :src="currentlyCrawling.screenshot_path"
                                :alt="currentlyCrawling.name || currentlyCrawling.domain"
                                class="size-full object-cover"
                            />
                            <div v-else class="flex size-full items-center justify-center">
                                <Globe class="size-5 text-muted-foreground" />
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col gap-1">
                            <Link
                                :href="`/sites/${currentlyCrawling.id}`"
                                class="font-semibold hover:text-primary transition-colors"
                            >
                                {{ currentlyCrawling.name || currentlyCrawling.domain }}
                            </Link>
                            <p class="text-sm text-muted-foreground">{{ currentlyCrawling.domain }}</p>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2 class="size-4 animate-spin" />
                            <span>Scanning...</span>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center gap-2 py-4 text-center text-muted-foreground">
                        <Clock class="size-8" />
                        <p>No site is currently being scanned.</p>
                        <p class="text-sm">The crawler is idle.</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Queued Sites -->
            <div>
                <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold">
                    <Clock class="size-5 text-muted-foreground" />
                    Waiting in Queue
                </h2>

                <Deferred data="queuedSites">
                    <template #fallback>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="i in 8"
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

                    <div class="flex flex-col gap-2">
                        <div class="mb-2 flex items-center justify-end">
                            <span class="rounded-full bg-muted px-2.5 py-0.5 text-sm font-medium text-muted-foreground">
                                {{ queuedSites.length }} sites
                            </span>
                        </div>

                        <div
                            v-for="(site, index) in queuedSites"
                            :key="site.id"
                            class="flex items-center gap-4 rounded-xl border bg-card p-4"
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
                                    :href="`/sites/${site.id}`"
                                    class="truncate text-sm font-medium hover:text-primary transition-colors"
                                >
                                    {{ site.name || site.domain }}
                                </Link>
                                <span class="truncate text-xs text-muted-foreground">{{ site.domain }}</span>
                            </div>
                            <HypeScoreBadge v-if="site.hype_score > 0" :score="site.hype_score" />
                            <span v-else class="text-xs text-muted-foreground">Pending</span>
                        </div>

                        <div v-if="queuedSites.length === 0" class="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center">
                            <Clock class="size-12 text-muted-foreground" />
                            <h3 class="text-lg font-medium">Queue is empty</h3>
                            <p class="text-muted-foreground">All sites have been crawled. Submit a new site to keep the hype going!</p>
                            <Link href="/submit">
                                <Button>Submit a Site</Button>
                            </Link>
                        </div>
                    </div>
                </Deferred>
            </div>
        </div>
    </GuestLayout>
</template>
