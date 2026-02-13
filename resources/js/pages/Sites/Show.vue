<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Globe, ExternalLink, ArrowLeft, Star, Clock, MessageSquare, User,
    Server, Shield, Cpu, AlertTriangle, Gauge, Accessibility,
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';
import HypeOMeter from '@/components/HypeOMeter.vue';
import HypeScoreBadge from '@/components/HypeScoreBadge.vue';
import JsonLd from '@/components/JsonLd.vue';
import ScoreBreakdown from '@/components/ScoreBreakdown.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/layouts/GuestLayout.vue';
import type { Site, ScoreAverages } from '@/types';

const props = defineProps<{
    site: Site;
    scoreAverages: ScoreAverages;
}>();

const ratingForm = useForm({
    score: 3,
    comment: '',
});

const mentionLimit = ref(5);
const allMentions = computed(() => props.site.latest_crawl_result?.mention_details ?? []);
const visibleMentions = computed(() => allMentions.value.slice(0, mentionLimit.value));
const hasMoreMentions = computed(() => mentionLimit.value < allMentions.value.length);

const hoveredStar = ref(0);
const hasRated = ref(false);

function getRatedSites(): number[] {
    try {
        return JSON.parse(localStorage.getItem('rated_sites') || '[]');
    } catch {
        return [];
    }
}

function markAsRated(siteId: number): void {
    const rated = getRatedSites();
    if (!rated.includes(siteId)) {
        rated.push(siteId);
        localStorage.setItem('rated_sites', JSON.stringify(rated));
    }
    hasRated.value = true;
}

onMounted(() => {
    hasRated.value = getRatedSites().includes(props.site.id);
});

const submitRating = () => {
    ratingForm.post(`/sites/${props.site.slug}/rate`, {
        preserveScroll: true,
        onSuccess: () => {
            markAsRated(props.site.id);
            ratingForm.reset();
        },
    });
};

const hypeLabels: Record<number, string> = {
    1: 'Meh - Not much hype here',
    2: 'Mild - Some AI sprinkles',
    3: 'Moderate - Definitely on the hype train',
    4: 'High - Full AI marketing mode',
    5: 'Maximum Hype - Peak AI buzzword overload',
};

const formattedDate = computed(() => {
    if (!props.site.last_crawled_at) return 'Never';
    const date = new Date(props.site.last_crawled_at);
    return date.toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
});

const formattedCreatedAt = computed(() => {
    const date = new Date(props.site.created_at);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
});

const jsonLd = computed(() => {
    const data: Record<string, unknown> = {
        '@type': 'WebSite',
        'name': props.site.name || props.site.domain,
        'url': props.site.url,
    };
    if (props.site.meta_description) {
        data.description = props.site.meta_description;
    }
    if (props.site.user_rating_count > 0) {
        data.aggregateRating = {
            '@type': 'AggregateRating',
            'ratingValue': props.site.user_rating_avg,
            'bestRating': 5,
            'worstRating': 1,
            'ratingCount': props.site.user_rating_count,
        };
    }
    return data;
});

function lighthouseColor(score: number | null | undefined): string {
    if (score == null) return 'text-muted-foreground';
    if (score >= 90) return 'text-green-600 dark:text-green-400';
    if (score >= 50) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
}

function lighthouseRingColor(score: number | null | undefined): string {
    if (score == null) return 'stroke-muted';
    if (score >= 90) return 'stroke-green-500';
    if (score >= 50) return 'stroke-amber-500';
    return 'stroke-red-500';
}

function impactColor(impact: string): string {
    switch (impact) {
        case 'critical': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'serious': return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
        case 'moderate': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        default: return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
    }
}

const crawlResult = computed(() => props.site.latest_crawl_result);
const hasLighthouseData = computed(() => crawlResult.value?.lighthouse_performance != null);
const hasAxeData = computed(() => crawlResult.value?.axe_violations_count != null);

const lighthouseCategories = computed(() => {
    const cr = crawlResult.value;
    if (!cr) return [];
    return [
        { label: 'Performance', score: cr.lighthouse_performance },
        { label: 'Accessibility', score: cr.lighthouse_accessibility },
        { label: 'Best Practices', score: cr.lighthouse_best_practices },
        { label: 'SEO', score: cr.lighthouse_seo },
    ];
});

const sortedViolations = computed(() => {
    const violations = crawlResult.value?.axe_violations_summary ?? [];
    const order: Record<string, number> = { critical: 0, serious: 1, moderate: 2, minor: 3 };
    return [...violations].sort((a, b) => (order[a.impact] ?? 4) - (order[b.impact] ?? 4));
});

const metaTitle = computed(() => `${props.site.name || props.site.domain} AI Hype Score`);

const metaDescription = computed(() => {
    const parts: string[] = [];
    parts.push(`${props.site.domain} has a Hype Score of ${props.site.hype_score}`);
    const mentions = props.site.latest_crawl_result?.ai_mention_count;
    if (mentions) {
        parts[0] += ` with ${mentions} AI mention${mentions !== 1 ? 's' : ''}`;
    }
    parts[0] += '.';
    if (props.site.meta_description) {
        parts.push(props.site.meta_description);
    }
    return parts.join(' ');
});

const ogImage = computed(() => {
    if (!props.site.screenshot_path) return null;
    return props.site.screenshot_path.startsWith('http')
        ? props.site.screenshot_path
        : `${window.location.origin}/storage/${props.site.screenshot_path}`;
});
</script>

<template>
    <Head :title="metaTitle">
        <meta name="description" :content="metaDescription" />
        <meta property="og:title" :content="metaTitle" />
        <meta property="og:description" :content="metaDescription" />
        <meta property="og:type" content="website" />
        <meta v-if="ogImage" property="og:image" :content="ogImage" />
        <meta name="twitter:card" :content="ogImage ? 'summary_large_image' : 'summary'" />
        <meta name="twitter:title" :content="metaTitle" />
        <meta name="twitter:description" :content="metaDescription" />
        <meta v-if="ogImage" name="twitter:image" :content="ogImage" />
    </Head>

    <JsonLd :data="jsonLd" />

    <GuestLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Back Link -->
            <Link href="/">
                <Button variant="ghost" size="sm" class="mb-6">
                    <ArrowLeft class="size-4" />
                    Back to Leaderboard
                </Button>
            </Link>

            <!-- Header -->
            <div class="mb-8 flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-3">
                        <Globe class="size-6 text-muted-foreground" />
                        <h1 class="text-3xl font-bold">{{ site.name || site.domain }}</h1>
                    </div>
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <a
                            :href="site.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center gap-1 text-sm hover:text-foreground transition-colors"
                        >
                            {{ site.domain }}
                            <ExternalLink class="size-3" />
                        </a>
                        <span class="text-xs">|</span>
                        <span class="flex items-center gap-1 text-xs">
                            <Clock class="size-3" />
                            Added {{ formattedCreatedAt }}
                        </span>
                    </div>
                    <p v-if="site.description" class="mt-1 max-w-xl text-muted-foreground">
                        {{ site.description }}
                    </p>
                </div>
                <HypeScoreBadge :score="site.hype_score" />
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="flex flex-col gap-8 lg:col-span-2">
                    <!-- Screenshot -->
                    <Card>
                        <CardContent>
                            <div class="relative aspect-video overflow-hidden rounded-lg border bg-muted">
                                <img
                                    v-if="site.screenshot_path"
                                    :src="site.screenshot_path"
                                    :alt="site.name || site.domain"
                                    class="size-full object-cover object-top"
                                />
                                <div v-else class="flex size-full items-center justify-center">
                                    <Globe class="size-16 text-muted-foreground/30" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Score Breakdown -->
                    <Card v-if="site.latest_crawl_result">
                        <CardHeader>
                            <CardTitle>Hype Score Analysis</CardTitle>
                            <CardDescription>
                                Last crawled {{ formattedDate }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ScoreBreakdown :crawl-result="site.latest_crawl_result" :averages="scoreAverages" />
                        </CardContent>
                    </Card>

                    <!-- Lighthouse Scores -->
                    <Card v-if="site.latest_crawl_result">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Gauge class="size-5" />
                                Lighthouse Scores
                            </CardTitle>
                            <CardDescription>
                                Performance and quality metrics from Google Lighthouse
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="hasLighthouseData" class="grid grid-cols-2 gap-6 sm:grid-cols-4">
                                <div
                                    v-for="cat in lighthouseCategories"
                                    :key="cat.label"
                                    class="flex flex-col items-center gap-2"
                                >
                                    <div class="relative size-20">
                                        <svg class="size-full -rotate-90" viewBox="0 0 36 36">
                                            <circle
                                                cx="18" cy="18" r="15.9155"
                                                fill="none"
                                                class="stroke-muted"
                                                stroke-width="3"
                                            />
                                            <circle
                                                cx="18" cy="18" r="15.9155"
                                                fill="none"
                                                :class="lighthouseRingColor(cat.score)"
                                                stroke-width="3"
                                                stroke-linecap="round"
                                                :stroke-dasharray="`${(cat.score ?? 0)} ${100 - (cat.score ?? 0)}`"
                                            />
                                        </svg>
                                        <span
                                            :class="['absolute inset-0 flex items-center justify-center text-lg font-bold', lighthouseColor(cat.score)]"
                                        >
                                            {{ cat.score ?? '—' }}
                                        </span>
                                    </div>
                                    <span class="text-center text-xs font-medium text-muted-foreground">
                                        {{ cat.label }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center gap-3 py-6">
                                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                    <div v-for="i in 4" :key="i" class="flex flex-col items-center gap-2">
                                        <div class="size-20 animate-pulse rounded-full bg-muted" />
                                        <div class="h-3 w-16 animate-pulse rounded bg-muted" />
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">Lighthouse audit pending...</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Accessibility Audit -->
                    <Card v-if="site.latest_crawl_result">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Accessibility class="size-5" />
                                Accessibility Audit
                            </CardTitle>
                            <CardDescription>
                                axe-core automated accessibility check results
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="hasAxeData">
                                <div class="mb-4 flex gap-4">
                                    <div class="flex-1 rounded-lg border p-3 text-center">
                                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                                            {{ crawlResult?.axe_violations_count }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">Violations</p>
                                    </div>
                                    <div class="flex-1 rounded-lg border p-3 text-center">
                                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                            {{ crawlResult?.axe_passes_count }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">Passed Rules</p>
                                    </div>
                                </div>
                                <div v-if="sortedViolations.length > 0" class="flex flex-col gap-2">
                                    <h4 class="text-sm font-medium">Violation Details</h4>
                                    <div
                                        v-for="violation in sortedViolations"
                                        :key="violation.id"
                                        class="rounded-lg border p-3"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium">{{ violation.id }}</p>
                                                <p class="mt-0.5 text-xs text-muted-foreground">
                                                    {{ violation.description }}
                                                </p>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-2">
                                                <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', impactColor(violation.impact)]">
                                                    {{ violation.impact }}
                                                </span>
                                                <span class="text-xs text-muted-foreground">
                                                    {{ violation.nodes_count }} {{ violation.nodes_count === 1 ? 'node' : 'nodes' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center gap-3 py-6">
                                <div class="flex w-full gap-4">
                                    <div class="h-20 flex-1 animate-pulse rounded-lg bg-muted" />
                                    <div class="h-20 flex-1 animate-pulse rounded-lg bg-muted" />
                                </div>
                                <p class="text-xs text-muted-foreground">Accessibility audit pending...</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Score History Chart -->
                    <Card v-if="site.score_histories && site.score_histories.length > 0">
                        <CardHeader>
                            <CardTitle>Score History</CardTitle>
                            <CardDescription>How the hype has changed over time</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex aspect-[2/1] items-center justify-center rounded-lg border border-dashed bg-muted/30 text-muted-foreground">
                                Score History Chart - Coming Soon
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Mention Details -->
                    <Card v-if="site.latest_crawl_result && site.latest_crawl_result.mention_details.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <MessageSquare class="size-5" />
                                AI Mentions Found
                            </CardTitle>
                            <CardDescription>
                                {{ site.latest_crawl_result.mention_details.length }} mentions detected on the page
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col gap-3">
                                <div
                                    v-for="(mention, i) in visibleMentions"
                                    :key="i"
                                    class="rounded-lg border p-3"
                                >
                                    <div class="flex items-start justify-between gap-4">
                                        <p class="text-sm font-medium">
                                            "{{ mention.text }}"
                                        </p>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <span v-if="mention.has_animation" class="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                                Animated
                                            </span>
                                            <span v-if="mention.has_glow" class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                Glowing
                                            </span>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Font size: {{ mention.font_size }}px | Context: {{ mention.context }}
                                    </p>
                                </div>
                                <Button
                                    v-if="hasMoreMentions"
                                    variant="outline"
                                    class="w-full"
                                    @click="mentionLimit += 10"
                                >
                                    Show More ({{ allMentions.length - mentionLimit }} remaining)
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="flex flex-col gap-6">
                    <!-- User Rating -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Hype-O-Meter</CardTitle>
                            <CardDescription>Community rating</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <HypeOMeter
                                :rating="site.user_rating_avg || 0"
                                :count="site.user_rating_count || 0"
                            />
                        </CardContent>
                    </Card>

                    <!-- Rate This Site -->
                    <Card v-if="!hasRated">
                        <CardHeader>
                            <CardTitle>Rate This Site</CardTitle>
                            <CardDescription>How hyped is it?</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitRating" class="flex flex-col gap-4">
                                <div>
                                    <Label>Your Hype Rating</Label>
                                    <div class="mt-2 flex items-center gap-1">
                                        <button
                                            v-for="star in 5"
                                            :key="star"
                                            type="button"
                                            @click="ratingForm.score = star"
                                            @mouseenter="hoveredStar = star"
                                            @mouseleave="hoveredStar = 0"
                                            class="rounded-sm p-0.5 transition-colors hover:bg-accent"
                                        >
                                            <Star
                                                :class="[
                                                    'size-6 transition-colors',
                                                    (hoveredStar || ratingForm.score) >= star
                                                        ? 'fill-yellow-400 text-yellow-400'
                                                        : 'text-muted-foreground',
                                                ]"
                                            />
                                        </button>
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ hypeLabels[ratingForm.score] }}
                                    </p>
                                </div>

                                <div>
                                    <Label for="comment">Comment (optional)</Label>
                                    <Input
                                        id="comment"
                                        v-model="ratingForm.comment"
                                        placeholder="Share your thoughts on the hype..."
                                        class="mt-1.5"
                                    />
                                </div>

                                <Button type="submit" :disabled="ratingForm.processing" class="w-full">
                                    {{ ratingForm.processing ? 'Submitting...' : 'Submit Rating' }}
                                </Button>

                                <p v-if="ratingForm.errors.score" class="text-sm text-destructive">
                                    {{ ratingForm.errors.score }}
                                </p>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Quick Stats -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Quick Stats</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl class="flex flex-col gap-3">
                                <div class="flex items-center justify-between">
                                    <dt class="text-sm text-muted-foreground">Status</dt>
                                    <dd>
                                        <span
                                            :class="[
                                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                                site.status === 'completed'
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-muted text-muted-foreground',
                                            ]"
                                        >
                                            {{ site.status }}
                                        </span>
                                    </dd>
                                </div>
                                <div v-if="site.consecutive_failures > 0" class="rounded-lg border border-amber-200 bg-amber-50 p-2.5 dark:border-amber-800 dark:bg-amber-950/30">
                                    <div class="flex items-start gap-2">
                                        <AlertTriangle class="mt-0.5 size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
                                        <p class="text-xs text-amber-700 dark:text-amber-300">
                                            Last {{ site.consecutive_failures === 1 ? 'crawl' : `${site.consecutive_failures} crawls` }} failed. Showing data from last successful crawl.
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-sm text-muted-foreground">Times Crawled</dt>
                                    <dd class="text-sm font-medium">{{ site.crawl_count }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-sm text-muted-foreground">Last Crawled</dt>
                                    <dd class="text-sm font-medium">{{ formattedDate }}</dd>
                                </div>
                                <div v-if="site.submitter" class="flex items-center justify-between">
                                    <dt class="text-sm text-muted-foreground">Submitted by</dt>
                                    <dd class="flex items-center gap-1 text-sm font-medium">
                                        <User class="size-3" />
                                        {{ site.submitter.name }}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <!-- Tech & Server Info -->
                    <Card v-if="site.tech_stack?.length || site.server_software || site.tls_issuer">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Cpu class="size-5" />
                                Site Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl class="flex flex-col gap-3">
                                <div v-if="site.tech_stack?.length">
                                    <dt class="mb-1.5 text-sm text-muted-foreground">Tech Stack</dt>
                                    <dd class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="tech in site.tech_stack"
                                            :key="tech"
                                            class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400"
                                        >
                                            {{ tech }}
                                        </span>
                                    </dd>
                                </div>
                                <div v-if="site.server_software" class="flex items-center justify-between">
                                    <dt class="flex items-center gap-1 text-sm text-muted-foreground">
                                        <Server class="size-3" />
                                        Server
                                    </dt>
                                    <dd class="text-sm font-medium">{{ site.server_software }}</dd>
                                </div>
                                <div v-if="site.tls_issuer" class="flex items-center justify-between">
                                    <dt class="flex items-center gap-1 text-sm text-muted-foreground">
                                        <Shield class="size-3" />
                                        TLS Issuer
                                    </dt>
                                    <dd class="text-sm font-medium">{{ site.tls_issuer }}</dd>
                                </div>
                                <div v-if="site.server_ip" class="flex items-center justify-between">
                                    <dt class="text-sm text-muted-foreground">Server IP</dt>
                                    <dd class="text-sm font-medium font-mono">{{ site.server_ip }}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <!-- Existing Ratings -->
                    <Card v-if="site.ratings && site.ratings.length > 0">
                        <CardHeader>
                            <CardTitle>Recent Ratings</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col gap-3">
                                <div
                                    v-for="rating in site.ratings"
                                    :key="rating.id"
                                    class="rounded-lg border p-3"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium">
                                            {{ rating.user?.name || 'Anonymous' }}
                                        </span>
                                        <div class="flex items-center gap-0.5">
                                            <Star
                                                v-for="s in 5"
                                                :key="s"
                                                :class="[
                                                    'size-3',
                                                    s <= rating.score
                                                        ? 'fill-yellow-400 text-yellow-400'
                                                        : 'text-muted-foreground',
                                                ]"
                                            />
                                        </div>
                                    </div>
                                    <p v-if="rating.comment" class="mt-1 text-sm text-muted-foreground">
                                        {{ rating.comment }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
