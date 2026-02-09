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
    latest_crawl_result?: CrawlResult;
    score_histories?: ScoreHistory[];
    ratings?: Rating[];
    submitter?: User;
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
    lighthouse_performance: number | null;
    lighthouse_accessibility: number | null;
    lighthouse_perf_bonus: number;
    lighthouse_a11y_bonus: number;
    animation_count: number;
    glow_effect_count: number;
    rainbow_border_count: number;
    ai_image_count: number;
    ai_image_score: number;
    ai_image_details: AiImageDetail[] | null;
    ai_image_hype_bonus: number;
    annotated_screenshot_path: string | null;
    created_at: string;
}

export interface MentionDetail {
    text: string;
    font_size: number;
    has_animation: boolean;
    has_glow: boolean;
    context: string;
}

export interface AiImageDetail {
    url: string;
    confidence: number;
    signals: string[];
    breakdown: {
        url_patterns: number;
        metadata: number;
        html_context: number;
        resolution: number;
        format_quirks: number;
    };
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
