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
    status: string;
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
    score_histories?: ScoreHistory[];
    ratings?: Rating[];
    submitter?: User;
}

export interface ScoreAverages {
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
    animation_count: number;
    glow_effect_count: number;
    rainbow_border_count: number;
    annotated_screenshot_path: string | null;
    redirect_chain: Array<{ url: string; status: number }> | null;
    final_url: string | null;
    response_time_ms: number | null;
    html_size_bytes: number | null;
    detected_tech_stack: string[] | null;
    created_at: string;
}

export interface MentionDetail {
    text: string;
    font_size: number;
    has_animation: boolean;
    has_glow: boolean;
    context: string;
}

export interface ScoreHistory {
    id: number;
    hype_score: number;
    ai_mention_count: number;
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

export interface AlgorithmFactor {
    name: string;
    description: string;
    weight: string;
    example: string;
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
