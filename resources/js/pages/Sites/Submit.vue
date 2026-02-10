<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Globe, Send, Info, List } from 'lucide-vue-next';
import { ref, computed } from 'vue';
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

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string })?.success);

const mode = ref<'single' | 'batch'>('single');

const singleForm = useForm({
    url: '',
    name: '',
    category: '',
});

const batchForm = useForm({
    urls: '',
});

const submitSingle = () => {
    singleForm.post('/submit', {
        onSuccess: () => {
            singleForm.reset();
        },
    });
};

const submitBatch = () => {
    batchForm.post('/submit/batch', {
        preserveScroll: true,
        onSuccess: () => {
            batchForm.reset();
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
                                <CardTitle>Submit {{ mode === 'batch' ? 'Sites' : 'a Site' }}</CardTitle>
                                <CardDescription>
                                    Know a site drowning in AI buzzwords? Submit it for crawling.
                                </CardDescription>
                            </div>
                        </div>

                        <!-- Mode Toggle -->
                        <div class="mt-4 flex rounded-lg border p-1">
                            <button
                                type="button"
                                :class="[
                                    'flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    mode === 'single'
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                ]"
                                @click="mode = 'single'"
                            >
                                <Globe class="size-3.5" />
                                Single
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    mode === 'batch'
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                ]"
                                @click="mode = 'batch'"
                            >
                                <List class="size-3.5" />
                                Batch
                            </button>
                        </div>
                    </CardHeader>

                    <!-- Single Submit -->
                    <form v-if="mode === 'single'" @submit.prevent="submitSingle">
                        <CardContent class="flex flex-col gap-4">
                            <div class="flex flex-col gap-1.5">
                                <Label for="url">Website URL *</Label>
                                <Input
                                    id="url"
                                    v-model="singleForm.url"
                                    type="url"
                                    placeholder="https://example.com"
                                    required
                                />
                                <p v-if="singleForm.errors.url" class="text-sm text-destructive">
                                    {{ singleForm.errors.url }}
                                </p>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <Label for="name">Site Name (optional)</Label>
                                <Input
                                    id="name"
                                    v-model="singleForm.name"
                                    type="text"
                                    placeholder="e.g. Acme AI Solutions"
                                />
                                <p v-if="singleForm.errors.name" class="text-sm text-destructive">
                                    {{ singleForm.errors.name }}
                                </p>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <Label for="category">Category (optional)</Label>
                                <select
                                    id="category"
                                    v-model="singleForm.category"
                                    class="border-input bg-transparent dark:bg-input/30 h-9 w-full rounded-md border px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm"
                                >
                                    <option value="">Auto-detect from site</option>
                                    <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                                        {{ cat.label }}
                                    </option>
                                </select>
                                <p v-if="singleForm.errors.category" class="text-sm text-destructive">
                                    {{ singleForm.errors.category }}
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

                        <CardFooter class="pt-4">
                            <Button type="submit" :disabled="singleForm.processing" class="w-full">
                                <Send class="size-4" />
                                {{ singleForm.processing ? 'Submitting...' : 'Submit for Crawling' }}
                            </Button>
                        </CardFooter>
                    </form>

                    <!-- Batch Submit -->
                    <form v-else @submit.prevent="submitBatch">
                        <CardContent class="flex flex-col gap-4">
                            <!-- Success Message -->
                            <div
                                v-if="flash"
                                class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-800 dark:border-green-900/50 dark:bg-green-900/10 dark:text-green-300"
                            >
                                {{ flash }}
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <Label for="urls">Website URLs *</Label>
                                <textarea
                                    id="urls"
                                    v-model="batchForm.urls"
                                    rows="8"
                                    placeholder="https://openai.com&#10;https://anthropic.com&#10;https://midjourney.com&#10;&#10;Or comma-separated: openai.com, anthropic.com"
                                    required
                                    class="border-input bg-transparent dark:bg-input/30 w-full rounded-md border px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm"
                                />
                                <p v-if="batchForm.errors.urls" class="text-sm text-destructive">
                                    {{ batchForm.errors.urls }}
                                </p>
                            </div>

                            <div class="flex items-start gap-2 rounded-lg border bg-muted/50 p-3 text-sm text-muted-foreground">
                                <Info class="mt-0.5 size-4 shrink-0" />
                                <p>
                                    Enter one URL per line, or separate with commas. URLs without
                                    <code class="rounded bg-muted px-1">https://</code> will have it added automatically.
                                    Duplicates and invalid URLs will be skipped.
                                </p>
                            </div>
                        </CardContent>

                        <CardFooter class="pt-4">
                            <Button type="submit" :disabled="batchForm.processing" class="w-full">
                                <Send class="size-4" />
                                {{ batchForm.processing ? 'Submitting...' : 'Submit All for Crawling' }}
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </div>
    </GuestLayout>
</template>
