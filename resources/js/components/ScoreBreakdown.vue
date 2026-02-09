<script setup lang="ts">
import type { CrawlResult, ScoreAverages } from '@/types';
import { computed } from 'vue';
import { MessageSquare, Type, Sparkles, Eye, Gauge, Accessibility, ImageIcon } from 'lucide-vue-next';

const props = defineProps<{
    crawlResult: CrawlResult;
    averages: ScoreAverages;
}>();

interface Factor {
    label: string;
    value: number;
    avg: number;
    icon: typeof MessageSquare;
    description: string;
}

const factors = computed<Factor[]>(() => [
    {
        label: 'AI Mentions',
        value: props.crawlResult.mention_score,
        avg: props.averages.mention_score,
        icon: MessageSquare,
        description: `${props.crawlResult.ai_mention_count} mentions found`,
    },
    {
        label: 'Font Size Bonus',
        value: props.crawlResult.font_size_score,
        avg: props.averages.font_size_score,
        icon: Type,
        description: 'Bigger text = more hype points',
    },
    {
        label: 'Animations',
        value: props.crawlResult.animation_score,
        avg: props.averages.animation_score,
        icon: Sparkles,
        description: `${props.crawlResult.animation_count} animated elements`,
    },
    {
        label: 'Visual Effects',
        value: props.crawlResult.visual_effects_score,
        avg: props.averages.visual_effects_score,
        icon: Eye,
        description: `${props.crawlResult.glow_effect_count} glows, ${props.crawlResult.rainbow_border_count} rainbow borders`,
    },
    {
        label: 'AI Images',
        value: props.crawlResult.ai_image_hype_bonus,
        avg: props.averages.ai_image_hype_bonus,
        icon: ImageIcon,
        description: `${props.crawlResult.ai_image_count} AI images detected (${props.crawlResult.ai_image_score}% confidence)`,
    },
    {
        label: 'Performance Penalty',
        value: props.crawlResult.lighthouse_perf_bonus,
        avg: props.averages.lighthouse_perf_bonus,
        icon: Gauge,
        description: props.crawlResult.lighthouse_performance !== null
            ? `Lighthouse: ${props.crawlResult.lighthouse_performance}/100`
            : 'Not measured',
    },
    {
        label: 'Accessibility Penalty',
        value: props.crawlResult.lighthouse_a11y_bonus,
        avg: props.averages.lighthouse_a11y_bonus,
        icon: Accessibility,
        description: props.crawlResult.lighthouse_accessibility !== null
            ? `Lighthouse: ${props.crawlResult.lighthouse_accessibility}/100`
            : 'Not measured',
    },
]);

const getBarWidth = (value: number, avg: number): number => {
    if (value === 0 && avg === 0) return 0;
    const scale = Math.max(avg * 2, value);
    return Math.min((value / scale) * 100, 100);
};

const getBarColor = (value: number, avg: number): string => {
    if (avg === 0) return value > 0 ? 'bg-primary' : 'bg-muted-foreground/30';
    const ratio = value / avg;
    if (ratio >= 2) return 'bg-red-500';
    if (ratio >= 1.25) return 'bg-orange-500';
    if (ratio >= 0.75) return 'bg-yellow-500';
    return 'bg-green-500';
};

const getComparisonLabel = (value: number, avg: number): string => {
    if (avg === 0) return value > 0 ? 'Above avg' : '';
    const ratio = value / avg;
    if (ratio >= 2) return `${Math.round(ratio)}x avg`;
    if (ratio >= 1.1) return `${Math.round((ratio - 1) * 100)}% above avg`;
    if (ratio >= 0.9) return 'Near avg';
    if (value === 0) return 'None';
    return `${Math.round((1 - ratio) * 100)}% below avg`;
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Score Breakdown</h3>
            <span class="text-2xl font-bold tabular-nums">
                {{ crawlResult.total_score }} pts
            </span>
        </div>

        <div class="flex flex-col gap-3">
            <div
                v-for="factor in factors"
                :key="factor.label"
                class="flex flex-col gap-1.5"
            >
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2">
                        <component :is="factor.icon" class="size-4 text-muted-foreground" />
                        <span class="font-medium">{{ factor.label }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-muted-foreground tabular-nums">avg {{ factor.avg }}</span>
                        <span class="font-semibold tabular-nums">{{ factor.value }} pts</span>
                    </div>
                </div>
                <div class="relative h-2 w-full overflow-hidden rounded-full bg-muted">
                    <div
                        :class="['h-full rounded-full transition-all duration-500', getBarColor(factor.value, factor.avg)]"
                        :style="{ width: `${getBarWidth(factor.value, factor.avg)}%` }"
                    />
                    <!-- Average marker -->
                    <div
                        v-if="factor.avg > 0"
                        class="absolute top-0 h-full w-px bg-foreground/30"
                        :style="{ left: '50%' }"
                        :title="`Average: ${factor.avg}`"
                    />
                </div>
                <div class="flex items-center justify-between">
                    <p class="text-xs text-muted-foreground">
                        {{ factor.description }}
                    </p>
                    <span v-if="getComparisonLabel(factor.value, factor.avg)" class="text-xs text-muted-foreground">
                        {{ getComparisonLabel(factor.value, factor.avg) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
