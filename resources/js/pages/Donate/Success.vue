<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Heart, PartyPopper, ArrowRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import GuestLayout from '@/layouts/GuestLayout.vue';
</script>

<template>
    <Head title="Thank You! - Most AI Mentions" />

    <GuestLayout>
        <div class="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 sm:px-6 lg:px-8">
            <Card class="w-full">
                <CardContent class="flex flex-col items-center gap-6 py-12 text-center">
                    <div class="flex size-20 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                        <PartyPopper class="size-10 text-green-600 dark:text-green-400" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <h1 class="text-3xl font-bold">Thank You!</h1>
                        <p class="text-lg text-muted-foreground">
                            Your donation has been received. You're officially a supporter of hype science.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 rounded-full border bg-muted/50 px-4 py-2 text-sm text-muted-foreground">
                        <Heart class="size-4 text-red-500" />
                        <span>Your generosity keeps the hype tracker running</span>
                    </div>

                    <div class="flex gap-3">
                        <Link href="/">
                            <Button>
                                <ArrowRight class="size-4" />
                                Back to Leaderboard
                            </Button>
                        </Link>
                        <Link href="/donate">
                            <Button variant="outline">
                                <Heart class="size-4" />
                                Donate Again
                            </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </GuestLayout>
</template>
