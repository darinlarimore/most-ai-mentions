<?php

namespace App\Console\Commands;

use App\Models\CrawlResult;
use App\Models\Site;
use App\Services\HypeScoreCalculator;
use Illuminate\Console\Command;

class RecalculateScores extends Command
{
    protected $signature = 'app:recalculate-scores';

    protected $description = 'Recalculate all crawl result scores and site hype scores using the current formula';

    public function handle(HypeScoreCalculator $calculator): int
    {
        $total = CrawlResult::count();
        $this->info("Recalculating scores for {$total} crawl result(s)...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        CrawlResult::query()
            ->with('site')
            ->chunkById(100, function ($results) use ($calculator, $bar) {
                foreach ($results as $crawlResult) {
                    $scores = $calculator->calculate(
                        $crawlResult->mention_details ?? [],
                        $crawlResult->animation_count ?? 0,
                        $crawlResult->glow_effect_count ?? 0,
                        $crawlResult->rainbow_border_count ?? 0,
                        $crawlResult->lighthouse_performance,
                        $crawlResult->lighthouse_accessibility,
                        $crawlResult->ai_image_count ?? 0,
                        $crawlResult->ai_image_score ?? 0,
                    );

                    $crawlResult->update([
                        'total_score' => $scores['total_score'],
                        'mention_score' => $scores['mention_score'],
                        'font_size_score' => $scores['font_size_score'],
                        'animation_score' => $scores['animation_score'],
                        'visual_effects_score' => $scores['visual_effects_score'],
                        'lighthouse_perf_bonus' => $scores['lighthouse_perf_bonus'],
                        'lighthouse_a11y_bonus' => $scores['lighthouse_a11y_bonus'],
                        'ai_image_hype_bonus' => $scores['ai_image_hype_bonus'] ?? 0,
                    ]);

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();

        // Update each site's hype_score from its latest crawl result
        $siteCount = Site::whereHas('latestCrawlResult')->count();
        $this->info("Updating hype scores for {$siteCount} site(s)...");

        Site::query()
            ->whereHas('latestCrawlResult')
            ->with('latestCrawlResult')
            ->chunkById(100, function ($sites) {
                foreach ($sites as $site) {
                    $site->update([
                        'hype_score' => $site->latestCrawlResult->total_score,
                    ]);
                }
            });

        $this->info('Done. All scores recalculated.');

        return self::SUCCESS;
    }
}
