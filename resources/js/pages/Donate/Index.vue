<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowLeft, Heart, Server, Bot, Zap, Coffee } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const presetAmounts = [5, 10, 25, 50];
const selectedAmount = ref<number | null>(10);
const customAmount = ref<string>('');
const isCustom = ref(false);

const donationAmount = computed(() => {
    if (isCustom.value) {
        const parsed = parseFloat(customAmount.value);
        return isNaN(parsed) ? 0 : parsed;
    }
    return selectedAmount.value || 0;
});

const selectPreset = (amount: number) => {
    selectedAmount.value = amount;
    isCustom.value = false;
    customAmount.value = '';
};

const selectCustom = () => {
    isCustom.value = true;
    selectedAmount.value = null;
};

const form = useForm({});

const handleDonate = () => {
    if (donationAmount.value <= 0) return;

    form.post(`/donate/session?amount=${donationAmount.value * 100}`, {
        preserveScroll: true,
    });
};

const perks = [
    {
        icon: Server,
        title: 'Server Costs',
        description: 'Keep the crawlers running and the scores updating.',
    },
    {
        icon: Bot,
        title: 'Crawler Development',
        description: 'Improve our hype detection algorithms and add new analysis features.',
    },
    {
        icon: Zap,
        title: 'Site Performance',
        description: 'Faster page loads and real-time crawl updates.',
    },
    {
        icon: Coffee,
        title: 'Caffeine Supply',
        description: 'The essential fuel for late-night AI hype tracking sessions.',
    },
];
</script>

<template>
    <Head title="Donate - Most AI Mentions" />

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
                    <Heart class="size-6 text-red-500" />
                    <h1 class="text-3xl font-bold">Support the Hype Tracker</h1>
                </div>
                <p class="mt-2 max-w-2xl text-muted-foreground">
                    Help us keep tracking the AI hype across the web. Your donation keeps the crawlers crawling
                    and the scores scoring.
                </p>
            </div>

            <div class="grid gap-8 lg:grid-cols-2">
                <!-- Donation Form -->
                <Card>
                    <CardHeader>
                        <CardTitle>Make a Donation</CardTitle>
                        <CardDescription>
                            Choose an amount to support the project
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-6">
                        <!-- Preset Amounts -->
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <button
                                v-for="amount in presetAmounts"
                                :key="amount"
                                :class="[
                                    'flex flex-col items-center gap-1 rounded-xl border p-4 text-center transition-all',
                                    selectedAmount === amount && !isCustom
                                        ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                                        : 'hover:border-primary/30 hover:bg-accent',
                                ]"
                                @click="selectPreset(amount)"
                            >
                                <span class="text-2xl font-bold">${{ amount }}</span>
                            </button>
                        </div>

                        <!-- Custom Amount -->
                        <div>
                            <button
                                :class="[
                                    'mb-2 w-full rounded-xl border p-3 text-center text-sm font-medium transition-all',
                                    isCustom
                                        ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                                        : 'hover:border-primary/30 hover:bg-accent',
                                ]"
                                @click="selectCustom"
                            >
                                Custom Amount
                            </button>
                            <div v-if="isCustom" class="flex items-center gap-2">
                                <span class="text-lg font-medium text-muted-foreground">$</span>
                                <Input
                                    v-model="customAmount"
                                    type="number"
                                    min="1"
                                    step="1"
                                    placeholder="Enter amount"
                                />
                            </div>
                        </div>
                    </CardContent>
                    <CardFooter>
                        <Button
                            class="w-full"
                            size="lg"
                            :disabled="donationAmount <= 0 || form.processing"
                            @click="handleDonate"
                        >
                            <Heart class="size-4" />
                            {{ form.processing ? 'Processing...' : `Donate $${donationAmount}` }}
                        </Button>
                    </CardFooter>
                </Card>

                <!-- What Donations Support -->
                <div class="flex flex-col gap-4">
                    <h3 class="text-lg font-semibold">What Your Donation Supports</h3>
                    <div class="flex flex-col gap-3">
                        <div
                            v-for="perk in perks"
                            :key="perk.title"
                            class="flex items-start gap-3 rounded-xl border p-4"
                        >
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                <component :is="perk.icon" class="size-5 text-primary" />
                            </div>
                            <div>
                                <h4 class="font-medium">{{ perk.title }}</h4>
                                <p class="text-sm text-muted-foreground">{{ perk.description }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border bg-muted/50 p-4 text-center">
                        <p class="text-sm text-muted-foreground">
                            Payments are securely processed through Stripe.
                            <br />
                            All donations are non-refundable.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
