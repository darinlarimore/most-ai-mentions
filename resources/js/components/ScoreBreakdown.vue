<script setup lang="ts">
import type { CrawlResult } from '@/types';
import { computed } from 'vue';
import { MessageSquare, Type, Sparkles, Eye, Gauge, Accessibility, ImageIcon } from 'lucide-vue-next';

const props = defineProps<{
    crawlResult: CrawlResult;
}>();

interface Factor {
    label: string;
    value: number;
    max: number;
    icon: typeof MessageSquare;
    description: string;
}

const factors = computed<Factor[]>(() => [
    {
        label: 'AI Mentions',
        value: props.crawlResult.mention_score,
        max: 500,
        icon: MessageSquare,
        description: `${props.crawlResult.ai_mention_count} mentions found`,
    },
    {
        label: 'Font Size Bonus',
        value: props.crawlResult.font_size_score,
        max: 200,
        icon: Type,
        description: 'Bigger text = more hype points',
    },
    {
        label: 'Animations',
        value: props.crawlResult.animation_score,
        max: 150,
        icon: Sparkles,
        description: `${props.crawlResult.animation_count} animated elements`,
    },
    {
        label: 'Visual Effects',
        value: props.crawlResult.visual_effects_score,
        max: 150,
        icon: Eye,
        description: `${props.crawlResult.glow_effect_count} glows, ${props.crawlResult.rainbow_border_count} rainbow borders`,
    },
    {
        label: 'AI Images',
        value: props.crawlResult.ai_image_hype_bonus,
        max: 200,
        icon: ImageIcon,
        description: `${props.crawlResult.ai_image_count} AI images detected (${props.crawlResult.ai_image_score}% confidence)`,
    },
    {
        label: 'Performance Penalty',
        value: props.crawlResult.lighthouse_perf_bonus,
        max: 50,
        icon: Gauge,
        description: props.crawlResult.lighthouse_performance !== null
            ? `Lighthouse: ${props.crawlResult.lighthouse_performance}/100`
            : 'Not measured',
    },
    {
        label: 'Accessibility Penalty',
        value: props.crawlResult.lighthouse_a11y_bonus,
        max: 50,
        icon: Accessibility,
        description: props.crawlResult.lighthouse_accessibility !== null
            ? `Lighthouse: ${props.crawlResult.lighthouse_accessibility}/100`
            : 'Not measured',
    },
]);

const getBarColor = (value: number, max: number): string => {
    const pct = (value / max) * 100;
    if (pct >= 75) return 'bg-red-500';
    if (pct >= 50) return 'bg-orange-500';
    if (pct >= 25) return 'bg-yellow-500';
    return 'bg-green-500';
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
                    <span class="font-semibold tabular-nums">
                        {{ factor.value }} / {{ factor.max }}
                    </span>
                </div>
                <div class="relative h-2 w-full overflow-hidden rounded-full bg-muted">
                    <div
                        :class="['h-full rounded-full transition-all duration-500', getBarColor(factor.value, factor.max)]"
                        :style="{ width: `${Math.min((factor.value / factor.max) * 100, 100)}%` }"
                    />
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ factor.description }}
                </p>
            </div>
        </div>
    </div>
</template>
