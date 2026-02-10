<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu, X } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { ref } from 'vue';

const mobileMenuOpen = ref(false);
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <header class="sticky top-0 z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-2">
                    <Link href="/" class="flex items-center gap-2 font-bold text-lg">
                        <span>Most AI Mentions</span>
                    </Link>
                </div>

                <nav class="hidden items-center gap-6 md:flex">
                    <Link href="/" class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
                        Leaderboard
                    </Link>
                    <Link href="/algorithm" class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
                        Algorithm
                    </Link>
                    <Link href="/crawl/live" class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
                        Live Crawl
                    </Link>
                    <Link href="/donate" class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
                        Donate
                    </Link>
                    <Link href="/submit" class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
                        Submit Site
                    </Link>
                </nav>

                <button
                    class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground hover:text-foreground md:hidden"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <X v-if="mobileMenuOpen" class="size-5" />
                    <Menu v-else class="size-5" />
                </button>
            </div>

            <div v-if="mobileMenuOpen" class="border-t md:hidden">
                <nav class="flex flex-col gap-1 p-4">
                    <Link href="/" class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
                        Leaderboard
                    </Link>
                    <Link href="/algorithm" class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
                        Algorithm
                    </Link>
                    <Link href="/crawl/live" class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
                        Live Crawl
                    </Link>
                    <Link href="/donate" class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
                        Donate
                    </Link>
                    <Link href="/submit" class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
                        Submit Site
                    </Link>
                </nav>
            </div>
        </header>

        <main>
            <slot />
        </main>

        <footer class="border-t">
            <div class="mx-auto flex max-w-7xl flex-col items-center gap-4 px-4 py-8 text-center text-sm text-muted-foreground sm:px-6 lg:px-8">
                <div class="flex items-center gap-2">
                    <span>Most AI Mentions</span>
                </div>
                <p>Tracking AI hype across the web, so you don't have to.</p>
                <div class="flex gap-4">
                    <Link href="/" class="hover:text-foreground">Leaderboard</Link>
                    <Link href="/algorithm" class="hover:text-foreground">Algorithm</Link>
                    <Link href="/donate" class="hover:text-foreground">Donate</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
