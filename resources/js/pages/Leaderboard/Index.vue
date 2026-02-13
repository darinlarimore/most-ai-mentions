<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Trophy, Cpu, FlaskConical, Radio, Users, Search, X, ArrowUpDown, Calendar } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import JsonLd from '@/components/JsonLd.vue';
import NewsletterForm from '@/components/NewsletterForm.vue';
import SiteCard from '@/components/SiteCard.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import GuestLayout from '@/layouts/GuestLayout.vue';
import type { Site, PaginatedData } from '@/types';

interface CategoryOption {
    value: string;
    label: string;
}

const periodOptions = [
    { value: 'all', label: 'All Time' },
    { value: 'today', label: 'Today' },
    { value: 'week', label: 'This Week' },
    { value: 'month', label: 'This Month' },
    { value: 'year', label: 'This Year' },
];

const sortOptions = [
    { value: 'hype_score', label: 'Hype Score' },
    { value: 'density', label: 'AI Density' },
    { value: 'mentions', label: 'Most Mentions' },
    { value: 'user_rating', label: 'User Rated' },
    { value: 'newest', label: 'Recently Crawled' },
    { value: 'recently_added', label: 'Recently Added' },
];

const props = defineProps<{
    sites: PaginatedData<Site>;
    search?: string;
    category?: string;
    period?: string;
    sort?: string;
    categories: CategoryOption[];
}>();

const searchQuery = ref(props.search || '');
const activeCategory = ref(props.category || '');
const activePeriod = ref(props.period || 'all');
const activeSort = ref(props.sort || 'hype_score');
let debounceTimer: ReturnType<typeof setTimeout>;

const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
    const params: Record<string, string | undefined> = {
        search: searchQuery.value || undefined,
        category: activeCategory.value || undefined,
        period: activePeriod.value !== 'all' ? activePeriod.value : undefined,
        sort: activeSort.value !== 'hype_score' ? activeSort.value : undefined,
        ...overrides,
    };
    router.get('/', params, { preserveState: true, preserveScroll: true });
};

watch(searchQuery, (value) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        applyFilters({ search: value || undefined });
    }, 300);
});

const selectCategory = (value: string) => {
    activeCategory.value = activeCategory.value === value ? '' : value;
    applyFilters({ category: activeCategory.value || undefined });
};

const selectPeriod = (value: string) => {
    activePeriod.value = value;
    applyFilters({ period: value !== 'all' ? value : undefined });
};

const selectSort = (value: string | number | bigint | Record<string, unknown> | null) => {
    if (typeof value !== 'string') return;
    activeSort.value = value;
    applyFilters({ sort: value !== 'hype_score' ? value : undefined });
};

const activeSortLabel = computed(() => {
    return sortOptions.find((o) => o.value === activeSort.value)?.label || 'Hype Score';
});

const leaderboardTitle = computed(() => {
    const titles: Record<string, string> = {
        hype_score: 'Hype Leaderboard',
        density: 'AI Density',
        mentions: 'Most Mentions',
        user_rating: 'User Rated',
        newest: 'Recently Crawled',
        recently_added: 'Recently Added',
    };
    return titles[activeSort.value] || 'Hype Leaderboard';
});

const startRank = computed(() => {
    return (props.sites.current_page - 1) * props.sites.per_page + 1;
});

const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url);
    }
};

const origin = typeof window !== 'undefined' ? window.location.origin : '';

const websiteJsonLd = computed(() => ({
    '@type': 'WebSite',
    'name': 'Most AI Mentions',
    'url': origin,
    'description': 'The definitive ranking of AI hype on the web. We crawl sites, count the buzzwords, and score the spectacle.',
    'potentialAction': {
        '@type': 'SearchAction',
        'target': `${origin}/?search={search_term_string}`,
        'query-input': 'required name=search_term_string',
    },
}));

const itemListJsonLd = computed(() => ({
    '@type': 'ItemList',
    'name': leaderboardTitle.value,
    'itemListOrder': 'https://schema.org/ItemListOrderDescending',
    'numberOfItems': props.sites.total,
    'itemListElement': props.sites.data.map((site, i) => ({
        '@type': 'ListItem',
        'position': startRank.value + i,
        'url': `${origin}/sites/${site.slug}`,
        'name': site.name || site.domain,
    })),
}));
</script>

<template>
    <JsonLd :data="websiteJsonLd" />
    <JsonLd :data="itemListJsonLd" />

    <Head title="AI Hype Leaderboard">
        <meta name="description" content="Which websites mention AI the most? See the live leaderboard ranking thousands of sites by hype score, AI mentions, animations, and visual effects." />
        <meta property="og:title" content="AI Hype Leaderboard | Most AI Mentions" />
        <meta property="og:description" content="Which websites mention AI the most? See the live leaderboard ranking thousands of sites by hype score, AI mentions, animations, and visual effects." />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" content="AI Hype Leaderboard | Most AI Mentions" />
        <meta name="twitter:description" content="Which websites mention AI the most? See the live leaderboard ranking thousands of sites by hype score, AI mentions, animations, and visual effects." />
    </Head>

    <GuestLayout>
        <!-- Hero Section -->
        <section class="border-b bg-gradient-to-b from-background to-muted/30">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center gap-6 text-center">
                    <div class="flex items-center gap-2 rounded-full border bg-background px-4 py-1.5 text-sm font-medium text-muted-foreground shadow-sm">
                        <Cpu class="size-4" />
                        <span>Tracking AI hype in real-time</span>
                    </div>

                    <AppLogoIcon class="size-32" />

                    <h1 class="max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                        Most AI Mentions
                    </h1>
                    <p class="max-w-xl text-lg text-muted-foreground">
                        The definitive ranking of AI hype on the web. We crawl sites, count the buzzwords, and score the spectacle.
                    </p>

                    <NewsletterForm />

                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <Link href="/submit">
                            <Button variant="outline" size="sm">
                                <FlaskConical class="size-4" />
                                Submit a Site
                            </Button>
                        </Link>
                        <Link href="/algorithm">
                            <Button variant="outline" size="sm">
                                <Cpu class="size-4" />
                                How it Works
                            </Button>
                        </Link>
                        <Link href="/crawl/live">
                            <Button variant="outline" size="sm">
                                <Radio class="size-4" />
                                Live Crawl
                            </Button>
                        </Link>
                        <Link href="/user-rated">
                            <Button variant="outline" size="sm">
                                <Users class="size-4" />
                                User Rated
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Leaderboard Section -->
        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="mb-8 flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <Trophy class="size-6 text-yellow-500" />
                    <h2 class="text-2xl font-bold">{{ leaderboardTitle }}</h2>
                    <span class="ml-auto text-sm text-muted-foreground">
                        {{ sites.total }} sites ranked
                    </span>
                </div>

                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search sites by name or domain..."
                        class="pl-10"
                    />
                </div>

                <!-- Period & Sort Filters -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-1.5">
                        <Calendar class="size-4 text-muted-foreground" />
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="opt in periodOptions"
                                :key="opt.value"
                                :class="[
                                    'inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                    activePeriod === opt.value
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background text-muted-foreground hover:border-primary/40 hover:text-foreground',
                                ]"
                                @click="selectPeriod(opt.value)"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>

                    <div class="ml-auto">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm">
                                    <ArrowUpDown class="size-4" />
                                    {{ activeSortLabel }}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuRadioGroup :model-value="activeSort" @update:model-value="selectSort">
                                    <DropdownMenuRadioItem v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <!-- Category Filters -->
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="cat in categories"
                        :key="cat.value"
                        :class="[
                            'inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                            activeCategory === cat.value
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background text-muted-foreground hover:border-primary/40 hover:text-foreground',
                        ]"
                        @click="selectCategory(cat.value)"
                    >
                        {{ cat.label }}
                        <X v-if="activeCategory === cat.value" class="size-3" />
                    </button>
                </div>
            </div>

            <div v-if="sites.data.length === 0" class="flex flex-col items-center gap-2 rounded-lg border border-dashed py-12 text-center">
                <Search class="size-8 text-muted-foreground/40" />
                <p class="text-sm text-muted-foreground">
                    No sites found{{ searchQuery ? ` matching "${searchQuery}"` : '' }}{{ activeCategory ? ` in ${activeCategory}` : '' }}
                </p>
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
