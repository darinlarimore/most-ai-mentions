export * from './auth';
export * from './navigation';
export * from './ui';

import type { Auth } from './auth';
import type { User } from './auth';

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
};

export interface Site {
    id: number;
    url: string;
    domain: string;
    slug: string;
    name: string | null;
    description: string | null;
    category: string | null;
    screenshot_path: string | null;
    hype_score: number;
    user_rating_avg: number;
    user_rating_count: number;
    crawl_count: number;
    source: string | null;
    status: string;
    consecutive_failures: number;
    last_crawled_at: string | null;
    submitted_by: number | null;
    created_at: string;
    tech_stack: string[] | null;
    server_ip: string | null;
    latitude: number | null;
    longitude: number | null;
    server_software: string | null;
    tls_issuer: string | null;
    page_title: string | null;
    meta_description: string | null;
    latest_crawl_result?: CrawlResult;
    latest_crawl_error?: CrawlError;
    score_histories?: ScoreHistory[];
    ratings?: Rating[];
    submitter?: User;
}

export interface ScoreAverages {
    density_score: number;
    mention_score: number;
    font_size_score: number;
    animation_score: number;
    visual_effects_score: number;
    total_score: number;
}

export interface CrawlResult {
    id: number;
    site_id: number;
    total_score: number;
    ai_mention_count: number;
    mention_details: MentionDetail[];
    mention_score: number;
    font_size_score: number;
    animation_score: number;
    visual_effects_score: number;
    total_word_count: number | null;
    ai_density_percent: number | null;
    density_score: number;
    animation_count: number;
    glow_effect_count: number;
    rainbow_border_count: number;
    redirect_chain: Array<{ url: string; status: number }> | null;
    final_url: string | null;
    response_time_ms: number | null;
    html_size_bytes: number | null;
    detected_tech_stack: string[] | null;
    axe_violations_count: number | null;
    axe_passes_count: number | null;
    axe_violations_summary: AxeViolation[] | null;
    lighthouse_performance: number | null;
    lighthouse_accessibility: number | null;
    lighthouse_best_practices: number | null;
    lighthouse_seo: number | null;
    created_at: string;
}

export interface MentionDetail {
    text: string;
    font_size: number;
    has_animation: boolean;
    has_glow: boolean;
    context: string;
    source?: 'body' | 'title' | 'meta_description';
}

export interface AxeViolation {
    id: string;
    impact: string;
    description: string;
    nodes_count: number;
}

export interface ScoreHistory {
    id: number;
    hype_score: number;
    ai_mention_count: number;
    lighthouse_performance: number | null;
    lighthouse_accessibility: number | null;
    recorded_at: string;
}

export interface Rating {
    id: number;
    user_id?: number;
    score: number;
    comment: string | null;
    user?: User;
    created_at: string;
}

export interface CrawlError {
    id: number;
    site_id: number;
    crawl_result_id: number | null;
    category: string;
    category_label: string;
    message: string | null;
    url: string | null;
    created_at: string;
}

export interface AlgorithmFactor {
    name: string;
    description: string;
    weight: string;
    example: string;
}

export interface CompanyList {
    name: string;
    slug: string;
    description: string;
    source_url: string | null;
}

export interface CompanyListLink {
    name: string;
    href: string;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
    first_page_url: string;
    last_page_url: string;
    path: string;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}
