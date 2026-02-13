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
                Log::warning("Lighthouse audit failed for {$domain}: {$errorOutput}");

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
     * Resolve the path to the Chrome executable from puppeteer's cache.
     */
    private function resolveChromePath(): ?string
    {
        $process = new Process([
            'node', '-e', "console.log(require('puppeteer').executablePath())",
        ]);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(10);
        $process->run();

        $path = trim($process->getOutput());

        return ($process->isSuccessful() && $path !== '' && file_exists($path)) ? $path : null;
    }

    /**
     * Run the Lighthouse CLI.
     *
     * @return array{0: bool, 1: string, 2: string} [success, stdout, stderr]
     */
    protected function runProcess(string $domain): array
    {
        $lighthouseBin = base_path('node_modules/.bin/lighthouse');

        $command = [
            $lighthouseBin,
            "https://{$domain}",
            '--output=json',
            '--chrome-flags=--headless=new --no-sandbox --disable-dev-shm-usage --disable-gpu',
            '--only-categories=performance,accessibility,best-practices,seo',
        ];

        $chromePath = $this->resolveChromePath();
        if ($chromePath) {
            $command[] = "--chrome-path={$chromePath}";
        }

        $process = new Process($command);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(120);
        $process->run();

        return [$process->isSuccessful(), $process->getOutput(), $process->getErrorOutput()];
    }
}
