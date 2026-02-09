<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import GuestLayout from '@/layouts/GuestLayout.vue';
import type { Site } from '@/types';
import HypeScoreBadge from '@/components/HypeScoreBadge.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Globe, Radio, ArrowLeft, Scan, Wifi, WifiOff } from 'lucide-vue-next';

defineProps<{
    currentSite: Site | null;
}>();

usePoll(5000);
</script>

<template>
    <Head title="Live Crawl - Most AI Mentions" />

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
                    <h1 class="text-3xl font-bold">Live Crawl</h1>
                    <span
                        v-if="currentSite"
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
            <Card v-if="currentSite" class="overflow-hidden">
                <div class="relative">
                    <!-- Scanning Animation Bar -->
                    <div class="absolute inset-x-0 top-0 h-1 overflow-hidden bg-muted">
                        <div class="animate-scan h-full w-1/3 bg-gradient-to-r from-transparent via-primary to-transparent" />
                    </div>

                    <CardHeader class="pt-6">
                        <CardTitle class="flex items-center gap-2">
                            <Scan class="size-5 animate-pulse text-primary" />
                            Currently Scanning
                        </CardTitle>
                        <CardDescription>
                            Analyzing {{ currentSite.domain }} for AI mentions, animations, and visual effects
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-6">
                        <!-- Site Info -->
                        <div class="flex items-center gap-4">
                            <div class="relative size-20 shrink-0 overflow-hidden rounded-xl border bg-muted">
                                <img
                                    v-if="currentSite.screenshot_path"
                                    :src="currentSite.screenshot_path"
                                    :alt="currentSite.name || currentSite.domain"
                                    class="size-full object-cover"
                                />
                                <div v-else class="flex size-full items-center justify-center">
                                    <Globe class="size-8 text-muted-foreground" />
                                </div>
                                <!-- Pulsing overlay -->
                                <div class="absolute inset-0 animate-pulse bg-primary/5" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <h3 class="text-xl font-semibold">
                                    {{ currentSite.name || currentSite.domain }}
                                </h3>
                                <p class="text-sm text-muted-foreground">{{ currentSite.url }}</p>
                                <div v-if="currentSite.hype_score > 0" class="mt-1">
                                    <HypeScoreBadge :score="currentSite.hype_score" />
                                </div>
                            </div>
                        </div>

                        <!-- Scanning Progress Indicators -->
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="flex items-center gap-3 rounded-lg border p-3">
                                <div class="flex size-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                    <Scan class="size-4 animate-pulse text-blue-600 dark:text-blue-400" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">Scanning Page</span>
                                    <span class="text-xs text-muted-foreground">Searching for AI keywords...</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 rounded-lg border p-3">
                                <div class="flex size-8 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                                    <Wifi class="size-4 animate-pulse text-purple-600 dark:text-purple-400" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">Analyzing Effects</span>
                                    <span class="text-xs text-muted-foreground">Checking for glows, animations...</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-center">
                            <Link :href="`/sites/${currentSite.id}`">
                                <Button variant="outline">
                                    View Site Details
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </div>
            </Card>

            <!-- No Active Crawl -->
            <Card v-else>
                <CardContent class="flex flex-col items-center gap-6 py-16 text-center">
                    <div class="flex size-20 items-center justify-center rounded-full bg-muted">
                        <WifiOff class="size-10 text-muted-foreground" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <h2 class="text-xl font-semibold">No Active Crawl</h2>
                        <p class="max-w-md text-muted-foreground">
                            The crawler is currently idle. Submit a site to start a new crawl, or check back later.
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <Link href="/submit">
                            <Button>Submit a Site</Button>
                        </Link>
                        <Link href="/crawl/queue">
                            <Button variant="outline">View Queue</Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
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
</style>
