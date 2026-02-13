<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Globe, Clock, MessageSquare, Percent } from 'lucide-vue-next';
import { computed } from 'vue';
import HypeScoreBadge from '@/components/HypeScoreBadge.vue';
import type { Site } from '@/types';

const props = defineProps<{
    site: Site;
    rank: number;
}>();

const categoryLabels: Record<string, string> = {
    marketing: 'Marketing', company: 'Company', tech: 'Tech', software: 'Software',
    saas: 'SaaS', agency: 'Agency', startup: 'Startup', enterprise: 'Enterprise',
    consulting: 'Consulting', ecommerce: 'E-commerce', finance: 'Finance',
    healthcare: 'Healthcare', education: 'Education', media: 'Media', blog: 'Blog', other: 'Other',
};

const categoryLabel = computed(() => {
    return props.site.category ? (categoryLabels[props.site.category] ?? props.site.category) : '';
});

const rankBadgeClass = computed(() => {
    if (props.rank === 1) return 'bg-yellow-400 text-yellow-900 shadow-yellow-400/30';
    if (props.rank === 2) return 'bg-gray-300 text-gray-800 shadow-gray-300/30';
    if (props.rank === 3) return 'bg-amber-600 text-amber-100 shadow-amber-600/30';
    return 'bg-muted text-muted-foreground';
});

const formattedDate = computed(() => {
    if (!props.site.last_crawled_at) return 'Never';
    const date = new Date(props.site.last_crawled_at);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
});
</script>

<template>
    <Link
        :href="`/sites/${site.slug}`"
        class="group flex flex-col overflow-hidden rounded-xl border bg-card transition-all hover:border-primary/20 hover:shadow-md dark:hover:border-primary/30"
    >
        <!-- Screenshot Banner -->
        <div class="relative h-32 w-full bg-muted sm:h-36">
            <img
                v-if="site.screenshot_path"
                :src="site.screenshot_path"
                :alt="site.name || site.domain"
                class="size-full object-cover object-top"
            />
            <div v-else class="flex size-full items-center justify-center">
                <Globe class="size-10 text-muted-foreground/40" />
            </div>

            <!-- Rank Badge -->
            <div
                :class="[
                    'absolute left-3 top-3 flex size-9 items-center justify-center rounded-full text-xs font-bold shadow-sm',
                    rankBadgeClass,
                ]"
            >
                #{{ rank }}
            </div>

            <!-- Category Badge -->
            <span
                v-if="site.category"
                class="absolute right-3 top-3 rounded-full bg-background/80 px-2 py-0.5 text-[10px] font-medium text-foreground backdrop-blur-sm"
            >
                {{ categoryLabel }}
            </span>
        </div>

        <!-- Card Body -->
        <div class="flex flex-1 flex-col gap-3 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 flex-col gap-0.5">
                    <h3 class="truncate font-semibold text-foreground transition-colors group-hover:text-primary">
                        {{ site.name || site.domain }}
                    </h3>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ site.domain }}
                    </p>
                </div>
                <div class="shrink-0">
                    <HypeScoreBadge :score="site.hype_score" />
                </div>
            </div>

            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                <span v-if="site.latest_crawl_result?.ai_density_percent != null" class="flex items-center gap-1">
                    <Percent class="size-3" />
                    {{ site.latest_crawl_result.ai_density_percent.toFixed(1) }}% AI
                </span>
                <span v-else class="flex items-center gap-1">
                    <MessageSquare class="size-3" />
                    {{ site.latest_crawl_result?.ai_mention_count ?? 0 }} mentions
                </span>
                <span class="flex items-center gap-1">
                    <Clock class="size-3" />
                    {{ formattedDate }}
                </span>
            </div>
        </div>
    </Link>
</template>
