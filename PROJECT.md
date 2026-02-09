# Most AI Mentions

A satirical web app that crawls websites, counts AI keyword mentions, and ranks them on a "Hype Score" leaderboard.

## Stack

- Laravel 12, Jetstream, Fortify
- Vue 3, Inertia v2, TypeScript
- Tailwind CSS 4, Reka UI
- Pest v4
- Spatie Crawler, Spatie Browsershot
- Laravel Cashier (Stripe), Laravel Reverb (WebSockets)

## Getting Started

```bash
nvm use                          # Node 22+ required
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run dev                      # or npm run build
php artisan serve
```

### Background Services

```bash
php artisan queue:work           # Process crawl jobs
php artisan reverb:start         # WebSocket server (port 8080)
```

## Key Services

| Service | Purpose |
|---------|---------|
| `HypeScoreCalculator` | Core scoring algorithm (mentions, font size, animations, glow, rainbow, Lighthouse) |
| `CrawlService` | Orchestrates crawl lifecycle (queue, cooldown, status) |
| `HtmlAnnotationService` | Highlights AI keywords in crawled HTML with score overlay |
| `AiContentDetectionService` | Free heuristic-based AI content detection |
| `LighthouseService` | Runs Lighthouse audits via CLI |
| `ScreenshotService` | Captures site screenshots via Browsershot |
| `NewsletterService` | Compiles and sends weekly top-sites newsletter |

## Scoring Algorithm

- **AI Keyword Mentions**: 10 points each (40+ keywords tracked)
- **Font Size Bonus**: 0.5 points per pixel above 16px baseline
- **Animations**: 15 points per animation (inline and page-wide)
- **Glow Effects**: 25 points per glow effect
- **Rainbow/Gradient Borders**: 30 points each
- **Lighthouse Performance**: `(100 - score) * 1.0` (worse perf = more points)
- **Lighthouse Accessibility**: `(100 - score) * 0.75` (worse a11y = more points)

## Artisan Commands

```bash
php artisan app:crawl-sites --limit=10   # Crawl sites ready for re-crawl
php artisan app:send-newsletter          # Compile and send weekly newsletter
```

## Test User

- Email: `test@example.com`
- Password: `password`

## Notes

- The `AiMentionCrawlObserver` class at `app/Crawlers/` needs to be created for actual crawling to work.
- Node 22+ is required (Vite 7). See `.nvmrc`.
- Queue driver is `database`. Reverb runs on port 8080.
- Stripe keys must be set in `.env` for donations to work.
