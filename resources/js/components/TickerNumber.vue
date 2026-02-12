<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{ value: number; compact?: boolean }>();

interface CharState {
    char: string;
    prevChar: string;
    rolling: boolean;
}

function formatCompact(n: number): string {
    if (n >= 1_000_000_000_000) return (n / 1_000_000_000_000).toFixed(1).replace(/\.0$/, '') + 'T';
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 10_000) return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'K';
    return n.toLocaleString();
}

function toChars(n: number): string[] {
    return (props.compact ? formatCompact(n) : n.toLocaleString()).split('');
}

const chars = ref<CharState[]>(toChars(props.value).map((c) => ({ char: c, prevChar: c, rolling: false })));
let timer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.value,
    (newVal) => {
        if (timer) clearTimeout(timer);

        const newChars = toChars(newVal);
        const oldChars = chars.value.map((c) => c.char);

        // Right-align old chars to match new length
        const padded = [...Array(Math.max(0, newChars.length - oldChars.length)).fill(''), ...oldChars].slice(
            -newChars.length,
        );

        chars.value = newChars.map((char, i) => ({
            char,
            prevChar: padded[i] ?? '',
            rolling: char !== (padded[i] ?? ''),
        }));

        timer = setTimeout(() => {
            chars.value = chars.value.map((c) => ({ ...c, rolling: false, prevChar: c.char }));
        }, 700);
    },
);
</script>

<template>
    <span class="inline-flex items-end tabular-nums">
        <span v-for="(d, i) in chars" :key="i" class="ticker-slot">
            <span
                v-if="d.rolling"
                class="ticker-roll"
                :style="{ animationDelay: `${(chars.length - 1 - i) * 50}ms` }"
            >
                <span class="ticker-char">{{ d.prevChar }}</span>
                <span class="ticker-char">{{ d.char }}</span>
            </span>
            <span v-else class="ticker-char">{{ d.char }}</span>
        </span>
    </span>
</template>

<style scoped>
.ticker-slot {
    display: inline-block;
    overflow: hidden;
    height: 1em;
    line-height: 1;
}

.ticker-char {
    display: block;
    height: 1em;
}

.ticker-roll {
    display: flex;
    flex-direction: column;
    animation: ticker-slide 0.45s ease-out forwards;
    animation-fill-mode: both;
}

@keyframes ticker-slide {
    from {
        transform: translateY(0);
    }
    to {
        transform: translateY(-1em);
    }
}
</style>
