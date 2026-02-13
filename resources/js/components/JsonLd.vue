<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    data: Record<string, unknown>;
}>();

const json = computed(() => JSON.stringify({ '@context': 'https://schema.org', ...props.data }));
</script>

<template>
    <component :is="'script'" type="application/ld+json" v-html="json" />
</template>
