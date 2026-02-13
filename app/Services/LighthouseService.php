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
        // Puppeteer v24+ is ESM-only, so we must use dynamic import() not require()
        $process = new Process([
            'node', '-e', "import('puppeteer').then(p => console.log(p.executablePath()))",
        ]);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(10);

        if (! $this->runWithHardTimeout($process, 15)) {
            Log::warning('Failed to resolve puppeteer Chrome path (timeout or error)', [
                'stderr' => $process->getErrorOutput(),
            ]);

            return null;
        }

        $path = trim($process->getOutput());

        if (! $process->isSuccessful() || $path === '') {
            Log::warning('Failed to resolve puppeteer Chrome path', [
                'stderr' => $process->getErrorOutput(),
            ]);

            return null;
        }

        return file_exists($path) ? $path : null;
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
        $process->setTimeout(90);

        // Set CHROME_PATH env var so Lighthouse's ChromeLauncher uses puppeteer's
        // Chrome instead of finding the snap-confined system chromium
        if ($chromePath) {
            $process->setEnv(['CHROME_PATH' => $chromePath]);
        }

        if (! $this->runWithHardTimeout($process, 100)) {
            return [false, '', 'Process killed after hard timeout'];
        }

        return [$process->isSuccessful(), $process->getOutput(), $process->getErrorOutput()];
    }

    /**
     * Start a process and poll for completion with a hard wall-clock kill.
     *
     * Uses start() + usleep polling instead of run() so that a hung
     * proc_get_status() call inside Process::wait() cannot block the
     * PHP worker indefinitely.
     */
    private function runWithHardTimeout(Process $process, int $timeoutSeconds): bool
    {
        $process->start();
        $deadline = microtime(true) + $timeoutSeconds;

        while ($process->isRunning()) {
            if (microtime(true) >= $deadline) {
                $process->stop(0);
                Log::warning('Process killed after hard timeout', [
                    'command' => $process->getCommandLine(),
                    'timeout' => $timeoutSeconds,
                ]);

                return false;
            }
            usleep(250_000); // 250ms poll interval
        }

        return true;
    }
}
