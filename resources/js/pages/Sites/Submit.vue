<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Globe, Send, Info } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/layouts/GuestLayout.vue';

interface CategoryOption {
    value: string;
    label: string;
}

defineProps<{
    categories: CategoryOption[];
}>();

const form = useForm({
    url: '',
    name: '',
    category: '',
});

const submit = () => {
    form.post('/submit', {
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Submit a Site" />

    <GuestLayout>
        <div class="flex flex-1 flex-col items-center justify-center p-4 py-12">
            <div class="w-full max-w-lg">
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10">
                                <Globe class="size-5 text-primary" />
                            </div>
                            <div>
                                <CardTitle>Submit a Site</CardTitle>
                                <CardDescription>
                                    Know a site drowning in AI buzzwords? Submit it for crawling.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>

                    <form @submit.prevent="submit">
                        <CardContent class="flex flex-col gap-4">
                            <div class="flex flex-col gap-1.5">
                                <Label for="url">Website URL *</Label>
                                <Input
                                    id="url"
                                    v-model="form.url"
                                    type="url"
                                    placeholder="https://example.com"
                                    required
                                />
                                <p v-if="form.errors.url" class="text-sm text-destructive">
                                    {{ form.errors.url }}
                                </p>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <Label for="name">Site Name (optional)</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="e.g. Acme AI Solutions"
                                />
                                <p v-if="form.errors.name" class="text-sm text-destructive">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <Label for="category">Category (optional)</Label>
                                <select
                                    id="category"
                                    v-model="form.category"
                                    class="border-input bg-transparent dark:bg-input/30 h-9 w-full rounded-md border px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm"
                                >
                                    <option value="">Auto-detect from site</option>
                                    <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                                        {{ cat.label }}
                                    </option>
                                </select>
                                <p v-if="form.errors.category" class="text-sm text-destructive">
                                    {{ form.errors.category }}
                                </p>
                            </div>

                            <div class="flex items-start gap-2 rounded-lg border bg-muted/50 p-3 text-sm text-muted-foreground">
                                <Info class="mt-0.5 size-4 shrink-0" />
                                <p>
                                    The site will be added to the crawl queue. Our crawler will visit the page,
                                    count AI mentions, analyze visual effects, and calculate the Hype Score.
                                    This usually takes a few minutes.
                                </p>
                            </div>
                        </CardContent>

                        <CardFooter>
                            <Button type="submit" :disabled="form.processing" class="w-full">
                                <Send class="size-4" />
                                {{ form.processing ? 'Submitting...' : 'Submit for Crawling' }}
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </div>
    </GuestLayout>
</template>
