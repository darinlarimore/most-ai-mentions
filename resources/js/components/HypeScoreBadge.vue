<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    score: number;
}>();

const pulseDelay = `${(Math.random() * 2).toFixed(2)}s`;

const tier = computed(() => {
    if (props.score >= 1000) return 'legendary';
    if (props.score >= 500) return 'high';
    if (props.score >= 100) return 'medium';
    return 'low';
});

const badgeClasses = computed(() => {
    switch (tier.value) {
        case 'legendary':
            return 'bg-gradient-to-r from-red-500 via-purple-500 via-blue-500 to-pink-500 text-white animate-pulse shadow-lg shadow-purple-500/30';
        case 'high':
            return 'bg-gradient-to-r from-red-500 to-orange-500 text-white shadow-md shadow-red-500/20';
        case 'medium':
            return 'bg-gradient-to-r from-yellow-400 to-orange-400 text-yellow-900 shadow-sm';
        default:
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
    }
});

const label = computed(() => {
    if (props.score >= 1000) return 'MAX HYPE';
    if (props.score >= 500) return 'HIGH';
    if (props.score >= 100) return 'MEDIUM';
    return 'LOW';
});
</script>

<template>
    <div class="inline-flex flex-col items-center gap-1">
        <span
            :class="[
                'inline-flex items-center justify-center rounded-full px-3 py-1 text-lg font-bold tabular-nums transition-all',
                badgeClasses,
                tier === 'legendary' ? 'ring-2 ring-purple-400/50 ring-offset-2 ring-offset-background' : '',
            ]"
            :style="tier === 'legendary' ? { animationDelay: pulseDelay } : undefined"
        >
            {{ score }}
        </span>
        <span
            :class="[
                'text-[10px] font-semibold uppercase tracking-wider',
                tier === 'legendary' ? 'bg-gradient-to-r from-red-500 via-purple-500 to-blue-500 bg-clip-text text-transparent' :
                tier === 'high' ? 'text-red-500' :
                tier === 'medium' ? 'text-orange-500' :
                'text-green-600 dark:text-green-400',
            ]"
        >
            {{ label }}
        </span>
    </div>
</template>
