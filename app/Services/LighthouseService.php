<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class LighthouseService
{
    /** @var int Timeout in seconds for the Lighthouse CLI process. */
    private const PROCESS_TIMEOUT = 60;

    /**
     * Run a Lighthouse audit against the given URL.
     *
     * Executes `npx lighthouse` in a headless Chrome environment and parses
     * the JSON output to extract performance and accessibility scores. Each
     * score is returned as an integer between 0 and 100.
     *
     * If the process fails or the output cannot be parsed, null values are
     * returned for the affected categories and a warning is logged.
     *
     * @param  string  $url  The fully-qualified URL to audit.
     * @return array{performance: int|null, accessibility: int|null}
     */
    public function run(string $url): array
    {
        $outputPath = storage_path('app/private/lighthouse-'.md5($url).'-'.now()->timestamp.'.json');

        try {
            $env = [];
            $chromePath = env('CHROME_PATH');
            if ($chromePath) {
                $env['CHROME_PATH'] = $chromePath;
            }

            $result = Process::timeout(self::PROCESS_TIMEOUT)->env($env)->run([
                'npx', 'lighthouse', $url,
                '--output=json',
                '--output-path='.$outputPath,
                '--chrome-flags=--headless --no-sandbox --disable-gpu',
                '--only-categories=performance,accessibility',
                '--quiet',
            ]);

            if (! $result->successful()) {
                $error = $result->errorOutput();

                // Chrome not available — throw so the job can retry
                if (str_contains($error, 'Unable to connect to Chrome') || str_contains($error, 'No Chrome installations found')) {
                    throw new RuntimeException("Chrome unavailable: {$error}");
                }

                Log::warning('Lighthouse process failed', [
                    'url' => $url,
                    'exit_code' => $result->exitCode(),
                    'error' => $error,
                ]);

                return ['performance' => null, 'accessibility' => null];
            }

            return $this->parseResults($outputPath);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Lighthouse audit threw an exception', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);

            return ['performance' => null, 'accessibility' => null];
        } finally {
            // Clean up the temporary JSON report
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    /**
     * Parse the Lighthouse JSON report and extract category scores.
     *
     * Lighthouse stores category scores as floats between 0 and 1;
     * this method converts them to integers between 0 and 100.
     *
     * @param  string  $jsonPath  Absolute path to the Lighthouse JSON output file.
     * @return array{performance: int|null, accessibility: int|null}
     */
    private function parseResults(string $jsonPath): array
    {
        if (! file_exists($jsonPath)) {
            Log::warning('Lighthouse output file not found', ['path' => $jsonPath]);

            return ['performance' => null, 'accessibility' => null];
        }

        $contents = file_get_contents($jsonPath);
        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            Log::warning('Lighthouse output is not valid JSON', ['path' => $jsonPath]);

            return ['performance' => null, 'accessibility' => null];
        }

        $categories = $data['categories'] ?? [];

        $performance = isset($categories['performance']['score'])
            ? (int) round($categories['performance']['score'] * 100)
            : null;

        $accessibility = isset($categories['accessibility']['score'])
            ? (int) round($categories['accessibility']['score'] * 100)
            : null;

        return [
            'performance' => $performance,
            'accessibility' => $accessibility,
        ];
    }
}
