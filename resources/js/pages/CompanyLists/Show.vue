<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Globe, ArrowLeft, Building2 } from 'lucide-vue-next';
import { computed } from 'vue';
import HypeScoreBadge from '@/components/HypeScoreBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import GuestLayout from '@/layouts/GuestLayout.vue';
import type { Site, CompanyList, PaginatedData } from '@/types';

const props = defineProps<{
    list: CompanyList;
    sites: PaginatedData<Site>;
    totalCompanies: number;
    matchedCount: number;
}>();

const startRank = computed(() => {
    return (props.sites.current_page - 1) * props.sites.per_page + 1;
});

const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url);
    }
};
</script>

<template>
    <Head :title="`${list.name} AI Mentions - Most AI Mentions`" />

    <GuestLayout>
        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <Link href="/">
                    <Button variant="ghost" size="sm" class="mb-4">
                        <ArrowLeft class="size-4" />
                        Back to Leaderboard
                    </Button>
                </Link>

                <div class="flex items-center gap-3">
                    <Building2 class="size-6 text-primary" />
                    <h1 class="text-3xl font-bold">{{ list.name }}</h1>
                </div>
                <p class="mt-2 text-muted-foreground">
                    {{ list.description }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ matchedCount }} of {{ totalCompanies }} companies tracked
                </p>
            </div>

            <!-- Site List -->
            <div class="flex flex-col gap-4">
                <Card
                    v-for="(site, index) in sites.data"
                    :key="site.id"
                >
                    <CardContent class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <!-- Rank + Screenshot -->
                        <div class="flex items-center gap-4">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground">
                                #{{ startRank + index }}
                            </div>
                            <div class="relative size-14 shrink-0 overflow-hidden rounded-lg border bg-muted">
                                <img
                                    v-if="site.screenshot_path"
                                    :src="site.screenshot_path"
                                    :alt="site.name || site.domain"
                                    class="size-full object-cover"
                                />
                                <div v-else class="flex size-full items-center justify-center">
                                    <Globe class="size-5 text-muted-foreground" />
                                </div>
                            </div>
                        </div>

                        <!-- Site Info -->
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <Link :href="`/sites/${site.slug}`" class="font-semibold hover:text-primary transition-colors">
                                {{ site.name || site.domain }}
                            </Link>
                            <p class="truncate text-sm text-muted-foreground">{{ site.domain }}</p>
                        </div>

                        <!-- Hype Score -->
                        <div class="shrink-0">
                            <HypeScoreBadge :score="site.hype_score" />
                        </div>
                    </CardContent>
                </Card>

                <div v-if="sites.data.length === 0" class="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center">
                    <Building2 class="size-12 text-muted-foreground" />
                    <h3 class="text-lg font-medium">No matched sites yet</h3>
                    <p class="text-muted-foreground">None of the {{ list.name }} companies have been crawled yet. Submit one to get started!</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="sites.last_page > 1" class="mt-8 flex items-center justify-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!sites.prev_page_url"
                    @click="goToPage(sites.prev_page_url)"
                >
                    Previous
                </Button>
                <span class="px-4 text-sm text-muted-foreground">
                    Page {{ sites.current_page }} of {{ sites.last_page }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!sites.next_page_url"
                    @click="goToPage(sites.next_page_url)"
                >
                    Next
                </Button>
            </div>
        </section>
    </GuestLayout>
</template>
