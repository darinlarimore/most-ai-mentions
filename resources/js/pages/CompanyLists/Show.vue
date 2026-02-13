<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Building2 } from 'lucide-vue-next';
import { computed } from 'vue';
import SiteCard from '@/components/SiteCard.vue';
import { Button } from '@/components/ui/button';
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

const metaDescription = computed(() => {
    return `AI hype scores for ${props.matchedCount} ${props.list.name} companies. See which ones mention AI the most on their websites.`;
});

const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url);
    }
};
</script>

<template>
    <Head :title="`${list.name} AI Leaderboard`">
        <meta name="description" :content="metaDescription" />
        <meta property="og:title" :content="`${list.name} AI Leaderboard | Most AI Mentions`" />
        <meta property="og:description" :content="metaDescription" />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" :content="`${list.name} AI Leaderboard | Most AI Mentions`" />
        <meta name="twitter:description" :content="metaDescription" />
    </Head>

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

            <!-- Site Grid -->
            <div v-if="sites.data.length === 0" class="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center">
                <Building2 class="size-12 text-muted-foreground" />
                <h3 class="text-lg font-medium">No matched sites yet</h3>
                <p class="text-muted-foreground">None of the {{ list.name }} companies have been crawled yet. Submit one to get started!</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <SiteCard
                    v-for="(site, index) in sites.data"
                    :key="site.id"
                    :site="site"
                    :rank="startRank + index"
                />
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
