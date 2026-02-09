<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Site } from '@/types';
import HypeScoreBadge from '@/components/HypeScoreBadge.vue';
import { Globe, Clock, MessageSquare } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    site: Site;
    rank: number;
}>();

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
        :href="`/sites/${site.id}`"
        class="group flex items-center gap-4 rounded-xl border bg-card p-4 transition-all hover:shadow-md hover:border-primary/20 dark:hover:border-primary/30"
    >
        <!-- Rank Badge -->
        <div
            :class="[
                'flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-bold shadow-sm',
                rankBadgeClass,
            ]"
        >
            #{{ rank }}
        </div>

        <!-- Screenshot Thumbnail -->
        <div class="relative size-16 shrink-0 overflow-hidden rounded-lg border bg-muted">
            <img
                v-if="site.screenshot_path"
                :src="site.screenshot_path"
                :alt="site.name || site.domain"
                class="size-full object-cover"
            />
            <div v-else class="flex size-full items-center justify-center">
                <Globe class="size-6 text-muted-foreground" />
            </div>
        </div>

        <!-- Site Info -->
        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <h3 class="truncate font-semibold text-foreground group-hover:text-primary transition-colors">
                {{ site.name || site.domain }}
            </h3>
            <p class="truncate text-sm text-muted-foreground">
                {{ site.domain }}
            </p>
            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                <span class="flex items-center gap-1">
                    <MessageSquare class="size-3" />
                    {{ site.latest_crawl_result?.ai_mention_count ?? 0 }} mentions
                </span>
                <span class="flex items-center gap-1">
                    <Clock class="size-3" />
                    {{ formattedDate }}
                </span>
            </div>
        </div>

        <!-- Hype Score -->
        <div class="shrink-0">
            <HypeScoreBadge :score="site.hype_score" />
        </div>
    </Link>
</template>
