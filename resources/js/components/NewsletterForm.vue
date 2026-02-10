<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Mail, CheckCircle2, AlertCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const form = useForm({
    email: '',
});

const status = ref<'idle' | 'success' | 'error'>('idle');
const message = ref('');

const submit = () => {
    form.post('/newsletter/subscribe', {
        preserveScroll: true,
        onSuccess: () => {
            status.value = 'success';
            message.value = 'You\'re subscribed! Welcome to the hype.';
            form.reset();
        },
        onError: () => {
            status.value = 'error';
            message.value = 'Something went wrong. Please try again.';
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="flex w-full max-w-md flex-col gap-2 sm:flex-row">
        <div class="relative flex-1">
            <Mail class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                v-model="form.email"
                type="email"
                placeholder="Enter your email for hype updates..."
                class="pl-9"
                required
            />
        </div>
        <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Subscribing...' : 'Subscribe' }}
        </Button>
    </form>
    <div v-if="status !== 'idle'" class="mt-2 flex items-center gap-1.5 text-sm">
        <CheckCircle2 v-if="status === 'success'" class="size-4 text-green-500" />
        <AlertCircle v-else class="size-4 text-destructive" />
        <span :class="status === 'success' ? 'text-green-600 dark:text-green-400' : 'text-destructive'">
            {{ message }}
        </span>
    </div>
    <p v-if="form.errors.email" class="mt-1 text-sm text-destructive">{{ form.errors.email }}</p>
</template>
