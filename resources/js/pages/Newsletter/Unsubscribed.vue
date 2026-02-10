<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MailX } from 'lucide-vue-next';
import GuestLayout from '@/layouts/GuestLayout.vue';
</script>

<template>
    <Head title="Unsubscribed" />

    <GuestLayout>
        <div class="flex flex-1 flex-col items-center justify-center p-4 py-24">
            <div class="flex flex-col items-center gap-4 text-center">
                <div class="flex size-16 items-center justify-center rounded-full bg-muted">
                    <MailX class="size-8 text-muted-foreground" />
                </div>
                <h1 class="text-2xl font-bold">You've been unsubscribed</h1>
                <p class="max-w-md text-muted-foreground">
                    You won't receive any more weekly newsletter emails from us.
                    You can always resubscribe from the leaderboard page.
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
