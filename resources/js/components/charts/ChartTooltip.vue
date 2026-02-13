<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';

const props = defineProps<{
    visible: boolean;
    x: number;
    y: number;
}>();

const el = ref<HTMLElement | null>(null);
const clampedX = ref(0);
const clampedY = ref(0);

watch(
    () => [props.x, props.y, props.visible] as const,
    async () => {
        if (!props.visible) return;
        // Let the DOM update so we can measure the tooltip
        await nextTick();
        const pad = 8;
        const w = el.value?.offsetWidth ?? 0;
        const h = el.value?.offsetHeight ?? 0;
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        clampedX.value = props.x + w + pad > vw ? props.x - w - pad : props.x;
        clampedY.value = props.y + h + pad > vh ? props.y - h - pad : props.y;
    },
    { immediate: true },
);
</script>

<template>
    <div
        ref="el"
        class="pointer-events-none fixed z-50 rounded-md border bg-popover px-3 py-1.5 text-sm text-popover-foreground shadow-md transition-[opacity,transform] duration-150"
        :class="visible ? 'translate-y-0 opacity-100' : 'translate-y-1 opacity-0'"
        :style="{ left: `${clampedX}px`, top: `${clampedY}px` }"
    >
        <slot />
    </div>
</template>
