<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    rating: number;
    count: number;
}>();

const percentage = computed(() => ((props.rating - 1) / 4) * 100);

const hypeLevel = computed(() => {
    if (props.rating >= 4.5) return { label: 'Maximum Hype', emoji: '&#x1F92F;', color: 'text-red-500' };
    if (props.rating >= 3.5) return { label: 'Very Hyped', emoji: '&#x1F525;', color: 'text-orange-500' };
    if (props.rating >= 2.5) return { label: 'Somewhat Hyped', emoji: '&#x1F914;', color: 'text-yellow-500' };
    if (props.rating >= 1.5) return { label: 'Mild Interest', emoji: '&#x1F610;', color: 'text-blue-500' };
    return { label: 'Meh', emoji: '&#x1F971;', color: 'text-muted-foreground' };
});

const meterColor = computed(() => {
    if (props.rating >= 4.5) return 'from-red-500 to-pink-500';
    if (props.rating >= 3.5) return 'from-orange-500 to-red-500';
    if (props.rating >= 2.5) return 'from-yellow-400 to-orange-500';
    if (props.rating >= 1.5) return 'from-blue-400 to-yellow-400';
    return 'from-gray-400 to-blue-400';
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                Hype-O-Meter
            </span>
            <span class="text-xs text-muted-foreground">
                {{ count }} {{ count === 1 ? 'rating' : 'ratings' }}
            </span>
        </div>

        <div class="relative h-4 w-full overflow-hidden rounded-full bg-muted">
            <div
                class="h-full rounded-full bg-gradient-to-r transition-all duration-500"
                :class="meterColor"
                :style="{ width: `${percentage}%` }"
            />
        </div>

        <div class="flex items-center justify-between text-xs">
            <span class="text-muted-foreground">Meh</span>
            <div class="flex items-center gap-1">
                <span :class="['font-semibold', hypeLevel.color]" v-html="hypeLevel.emoji" />
                <span :class="['font-semibold', hypeLevel.color]">
                    {{ hypeLevel.label }}
                </span>
                <span class="ml-1 font-bold tabular-nums" :class="hypeLevel.color">
                    {{ rating.toFixed(1) }}
                </span>
            </div>
            <span class="text-muted-foreground">Maximum Hype</span>
        </div>
    </div>
</template>
