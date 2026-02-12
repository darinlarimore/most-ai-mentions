<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Globe, ExternalLink, ArrowLeft, Star, Clock, MessageSquare, User,
    Server, Shield, Cpu,
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';
import HypeOMeter from '@/components/HypeOMeter.vue';
import HypeScoreBadge from '@/components/HypeScoreBadge.vue';
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
</script>

<template>
    <Head :title="`${site.name || site.domain} - Most AI Mentions`" />

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
                                                site.status === 'active'
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-muted text-muted-foreground',
                                            ]"
                                        >
                                            {{ site.status }}
                                        </span>
                                    </dd>
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
