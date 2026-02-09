<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { Site, CrawlResult } from '@/types';
import { Button } from '@/components/ui/button';
import { ArrowLeft, ExternalLink, AlertTriangle } from 'lucide-vue-next';
import { ref, onMounted, watch } from 'vue';

const props = defineProps<{
    site: Site;
    annotatedHtml: string;
    crawlResult: CrawlResult | null;
}>();

const iframeRef = ref<HTMLIFrameElement | null>(null);
const iframeHeight = ref('100vh');

const updateIframeContent = () => {
    if (!iframeRef.value || !props.annotatedHtml) return;

    const doc = iframeRef.value.contentDocument;
    if (!doc) return;

    doc.open();
    doc.write(props.annotatedHtml);
    doc.close();

    // Adjust iframe height to content after load
    iframeRef.value.onload = () => {
        if (iframeRef.value?.contentDocument?.body) {
            const height = iframeRef.value.contentDocument.body.scrollHeight;
            iframeHeight.value = Math.max(height, 600) + 'px';
        }
    };
};

onMounted(() => {
    updateIframeContent();
});

watch(() => props.annotatedHtml, () => {
    updateIframeContent();
});
</script>

<template>
    <Head :title="`Annotated View - ${site.name || site.domain}`" />

    <div class="min-h-screen bg-background">
        <!-- Top Bar -->
        <div class="sticky top-0 z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-3">
                    <Link :href="`/sites/${site.id}`">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft class="size-4" />
                            Back to {{ site.name || site.domain }}
                        </Button>
                    </Link>
                    <div class="hidden items-center gap-2 sm:flex">
                        <span class="text-sm text-muted-foreground">Annotated View</span>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                            {{ crawlResult?.ai_mention_count ?? 0 }} AI mentions
                        </span>
                        <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                            Score: {{ crawlResult?.total_score ?? 0 }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        :href="site.url"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Button variant="outline" size="sm">
                            Visit Original
                            <ExternalLink class="ml-1 size-3" />
                        </Button>
                    </a>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="mx-auto max-w-7xl px-4 py-2">
            <div class="flex flex-wrap items-center gap-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                <span class="font-semibold">Legend:</span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-6 rounded" style="background: linear-gradient(135deg, #fbbf24, #f59e0b);"></span>
                    AI Keyword (hover for points)
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block size-3 rounded-full bg-purple-600"></span>
                    Animated mention
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block size-3 rounded-full bg-amber-600"></span>
                    Glow effect
                </span>
                <span>Score panel is in the top-right corner of the preview below.</span>
            </div>
        </div>

        <!-- Annotated Content -->
        <div class="mx-auto max-w-7xl px-4 py-4">
            <div v-if="annotatedHtml" class="overflow-hidden rounded-xl border shadow-lg">
                <iframe
                    ref="iframeRef"
                    sandbox="allow-same-origin"
                    class="w-full border-0"
                    :style="{ height: iframeHeight, minHeight: '600px' }"
                    title="Annotated site view"
                ></iframe>
            </div>

            <div v-else class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed py-20">
                <AlertTriangle class="size-12 text-muted-foreground/40" />
                <div class="text-center">
                    <h3 class="text-lg font-semibold">No Annotated View Available</h3>
                    <p class="mt-1 max-w-md text-sm text-muted-foreground">
                        This site hasn't been crawled yet, or the crawled HTML wasn't captured.
                        Submit a re-crawl to generate an annotated view.
                    </p>
                </div>
                <Link :href="`/sites/${site.id}`">
                    <Button variant="outline">Back to Site Details</Button>
                </Link>
            </div>
        </div>
    </div>
</template>
