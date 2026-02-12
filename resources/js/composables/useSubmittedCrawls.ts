import { ref, computed, watch } from 'vue';

export interface SubmittedCrawl {
    siteId: number;
    url: string;
    slug: string;
    status: 'queued' | 'crawling' | 'completed' | 'failed';
    step?: string;
    hypeScore?: number;
    submittedAt: string;
}

const STORAGE_KEY = 'submitted_crawls';
const STALE_HOURS = 24;

const crawls = ref<SubmittedCrawl[]>([]);
const echoSetup = ref(false);

function loadFromStorage(): void {
    if (typeof window === 'undefined') return;

    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;

        const parsed = JSON.parse(raw) as SubmittedCrawl[];
        const cutoff = Date.now() - STALE_HOURS * 60 * 60 * 1000;

        crawls.value = parsed.filter((c) => new Date(c.submittedAt).getTime() > cutoff);
    } catch {
        crawls.value = [];
    }
}

function persistToStorage(): void {
    if (typeof window === 'undefined') return;

    localStorage.setItem(STORAGE_KEY, JSON.stringify(crawls.value));
}

watch(crawls, persistToStorage, { deep: true });

function addCrawl(site: { id: number; url: string; slug: string }): void {
    if (crawls.value.some((c) => c.siteId === site.id)) return;

    crawls.value.push({
        siteId: site.id,
        url: site.url,
        slug: site.slug,
        status: 'queued',
        submittedAt: new Date().toISOString(),
    });
}

function addCrawls(sites: Array<{ id: number; url: string; slug: string }>): void {
    for (const site of sites) {
        addCrawl(site);
    }
}

function removeCrawl(siteId: number): void {
    crawls.value = crawls.value.filter((c) => c.siteId !== siteId);
}

function setupEcho(): void {
    if (echoSetup.value || typeof window === 'undefined') return;
    echoSetup.value = true;

    loadFromStorage();

    const channel = window.Echo.channel('crawl-activity');

    channel.listen('.CrawlStarted', (e: { site_id: number }) => {
        const crawl = crawls.value.find((c) => c.siteId === e.site_id);
        if (crawl) {
            crawl.status = 'crawling';
            crawl.step = undefined;
        }
    });

    channel.listen('.CrawlProgress', (e: { site_id: number; step: string; message: string }) => {
        const crawl = crawls.value.find((c) => c.siteId === e.site_id);
        if (crawl) {
            crawl.status = 'crawling';
            crawl.step = e.message;
        }
    });

    channel.listen('.CrawlCompleted', (e: { site_id: number; hype_score: number; has_error: boolean }) => {
        const crawl = crawls.value.find((c) => c.siteId === e.site_id);
        if (crawl) {
            if (e.has_error && e.hype_score === 0) {
                crawl.status = 'failed';
            } else {
                crawl.status = 'completed';
            }
            crawl.hypeScore = e.hype_score;
            crawl.step = undefined;
        }
    });
}

const nearestCrawl = computed<SubmittedCrawl | null>(() => {
    const active = crawls.value.filter((c) => c.status === 'crawling' || c.status === 'queued');
    if (active.length === 0) return null;

    // Prefer crawling over queued, then earliest submittedAt
    return active.sort((a, b) => {
        if (a.status === 'crawling' && b.status !== 'crawling') return -1;
        if (b.status === 'crawling' && a.status !== 'crawling') return 1;
        return new Date(a.submittedAt).getTime() - new Date(b.submittedAt).getTime();
    })[0];
});

const hasActiveCrawls = computed(() => crawls.value.some((c) => c.status === 'queued' || c.status === 'crawling'));

export function useSubmittedCrawls() {
    return {
        crawls,
        nearestCrawl,
        hasActiveCrawls,
        addCrawl,
        addCrawls,
        removeCrawl,
        setupEcho,
        loadFromStorage,
    };
}
