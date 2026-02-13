<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class LighthouseService
{
    /**
     * Run a Lighthouse audit on the given domain.
     *
     * @return array{performance: int, accessibility: int, best_practices: int, seo: int}|null
     */
    public function audit(string $domain): ?array
    {
        try {
            [$success, $output, $errorOutput] = $this->runProcess($domain);

            if (! $success) {
                Log::warning("Lighthouse audit failed for {$domain}", [
                    'stderr' => mb_substr($errorOutput, 0, 1000),
                ]);

                return null;
            }

            $result = $this->parseOutput($output);

            if ($result === null) {
                Log::warning("Lighthouse audit returned invalid output for {$domain}", [
                    'output' => mb_substr($output, 0, 500),
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning("Lighthouse audit exception for {$domain}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Parse the JSON output from Lighthouse.
     *
     * @return array{performance: int, accessibility: int, best_practices: int, seo: int}|null
     */
    public function parseOutput(string $output): ?array
    {
        $data = json_decode($output, true);

        if (! is_array($data) || ! isset($data['categories'])) {
            return null;
        }

        $categories = $data['categories'];

        return [
            'performance' => $this->extractScore($categories, 'performance'),
            'accessibility' => $this->extractScore($categories, 'accessibility'),
            'best_practices' => $this->extractScore($categories, 'best-practices'),
            'seo' => $this->extractScore($categories, 'seo'),
        ];
    }

    /**
     * Extract a 0-100 score from a Lighthouse category.
     */
    private function extractScore(array $categories, string $key): int
    {
        $score = $categories[$key]['score'] ?? null;

        // Lighthouse returns scores as 0-1 floats
        return $score !== null ? (int) round($score * 100) : 0;
    }

    /**
     * Run the Lighthouse CLI.
     *
     * @return array{0: bool, 1: string, 2: string} [success, stdout, stderr]
     */
    protected function runProcess(string $domain): array
    {
        $process = new Process([
            'npx', 'lighthouse',
            "https://{$domain}",
            '--output=json',
            '--chrome-flags=--headless --no-sandbox',
            '--only-categories=performance,accessibility,best-practices,seo',
        ]);
        $process->setTimeout(120);
        $process->run();

        return [$process->isSuccessful(), $process->getOutput(), $process->getErrorOutput()];
    }
}
