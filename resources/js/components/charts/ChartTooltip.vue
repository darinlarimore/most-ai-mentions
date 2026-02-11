<script setup lang="ts">
const props = defineProps<{
    visible: boolean;
    x: number;
    y: number;
}>();
</script>

<template>
    <div
        class="pointer-events-none fixed z-50 rounded-md border bg-popover px-3 py-1.5 text-sm text-popover-foreground shadow-md transition-[opacity,transform] duration-150"
        :class="visible ? 'translate-y-0 opacity-100' : 'translate-y-1 opacity-0'"
        :style="{ left: `${x}px`, top: `${y}px` }"
    >
        <slot />
    </div>
</template>
