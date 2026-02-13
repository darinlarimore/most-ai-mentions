<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft, Cpu, MessageSquare, Type, Sparkles, Eye,
    Zap, Brain, Percent,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import JsonLd from '@/components/JsonLd.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import type { AlgorithmFactor } from '@/types';
const props = defineProps<{
    factors: AlgorithmFactor[];
}>();

const faqJsonLd = computed(() => ({
    '@type': 'FAQPage',
    'mainEntity': props.factors.map((factor) => ({
        '@type': 'Question',
        'name': `How does "${factor.name}" affect the Hype Score?`,
        'acceptedAnswer': {
            '@type': 'Answer',
            'text': `${factor.description} Weight: ${factor.weight}. Example: ${factor.example}`,
        },
    })),
}));

const factorIcons: Record<string, typeof MessageSquare> = {
    'AI Buzzword Density': Percent,
    'AI Mentions': MessageSquare,
    'Font Size': Type,
    'Animations': Sparkles,
    'Visual Effects': Eye,
};

const getIcon = (name: string) => {
    for (const [key, icon] of Object.entries(factorIcons)) {
        if (name.toLowerCase().includes(key.toLowerCase())) return icon;
    }
    return Zap;
};
</script>

<template>
    <Head title="How the Algorithm Works">
        <meta name="description" content="Learn how we calculate AI hype scores. Our algorithm analyzes mentions, font sizes, animations, and visual effects to rank websites." />
        <meta property="og:title" content="How the Algorithm Works | Most AI Mentions" />
        <meta property="og:description" content="Learn how we calculate AI hype scores. Our algorithm analyzes mentions, font sizes, animations, and visual effects to rank websites." />
        <meta property="og:type" content="article" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" content="How the Algorithm Works | Most AI Mentions" />
        <meta name="twitter:description" content="Learn how we calculate AI hype scores. Our algorithm analyzes mentions, font sizes, animations, and visual effects to rank websites." />
    </Head>

    <JsonLd :data="faqJsonLd" />

    <GuestLayout>
        <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <Link href="/">
                    <Button variant="ghost" size="sm" class="mb-4">
                        <ArrowLeft class="size-4" />
                        Back to Leaderboard
                    </Button>
                </Link>

                <div class="flex items-center gap-3">
                    <Brain class="size-6 text-primary" />
                    <h1 class="text-3xl font-bold">The Hype Score Algorithm</h1>
                </div>
                <p class="mt-2 max-w-2xl text-muted-foreground">
                    Ever wonder how we quantify AI hype? Here's the breakdown of our
                    totally scientific and definitely not arbitrary scoring system.
                </p>
            </div>

            <!-- Overview Card -->
            <Card class="mb-8">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Cpu class="size-5" />
                        How It Works
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="prose prose-sm max-w-none text-muted-foreground dark:prose-invert">
                        <p>
                            Our crawler visits each submitted website and performs a comprehensive analysis.
                            It searches for AI-related buzzwords, measures how prominently they're displayed,
                            and checks for flashy animations and visual effects.
                        </p>
                        <p class="mt-3">
                            The final <strong class="text-foreground">Hype Score</strong> is a combination of all these factors.
                            Higher scores mean more AI hype. It's that simple (and that ridiculous).
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Factors Grid -->
            <div class="grid gap-4 md:grid-cols-2">
                <Card
                    v-for="factor in factors"
                    :key="factor.name"
                    class="transition-shadow hover:shadow-md"
                >
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10">
                                <component :is="getIcon(factor.name)" class="size-5 text-primary" />
                            </div>
                            <div>
                                <CardTitle class="text-base">{{ factor.name }}</CardTitle>
                                <CardDescription>
                                    Weight: <span class="font-semibold text-foreground">{{ factor.weight }}</span>
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <p class="text-sm text-muted-foreground">
                            {{ factor.description }}
                        </p>
                        <div class="rounded-lg border bg-muted/50 p-3">
                            <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Example</span>
                            <p class="mt-1 text-sm italic text-muted-foreground">
                                "{{ factor.example }}"
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <div v-if="factors.length === 0" class="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center">
                <Cpu class="size-12 text-muted-foreground" />
                <h3 class="text-lg font-medium">Algorithm details coming soon</h3>
                <p class="text-muted-foreground">We're still fine-tuning our hype detection system.</p>
            </div>

            <!-- Formula Section -->
            <Card class="mt-8">
                <CardHeader>
                    <CardTitle>The Formula</CardTitle>
                    <CardDescription>For the mathematically curious</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto rounded-lg bg-muted p-6 font-mono text-sm">
                        <code>
                            Hype Score = Density Score + Mention Score + Font Size Bonus + Animation Score + Visual Effects Score
                        </code>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">
                        AI Buzzword Density is the primary factor (up to 1,000 pts), measuring what percentage
                        of a page's words are AI buzzwords. This normalizes for page size so small and large
                        sites compete fairly. Mentions, font sizes, animations, and visual effects add bonus points.
                    </p>
                </CardContent>
            </Card>
        </div>
    </GuestLayout>
</template>
