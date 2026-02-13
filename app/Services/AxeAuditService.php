<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class AxeAuditService
{
    /**
     * Run an axe-core accessibility audit on the given domain.
     *
     * @return array{violations_count: int, passes_count: int, violations_summary: array<int, array{id: string, impact: string, description: string, nodes_count: int}>}|null
     */
    public function audit(string $domain): ?array
    {
        try {
            [$success, $output, $errorOutput] = $this->runProcess($domain);

            if (! $success) {
                Log::warning("axe-core audit failed for {$domain}", [
                    'stderr' => $errorOutput,
                ]);

                return null;
            }

            $result = $this->parseOutput($output);

            if ($result === null) {
                Log::warning("axe-core audit returned invalid output for {$domain}", [
                    'output' => mb_substr($output, 0, 500),
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning("axe-core audit exception for {$domain}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Parse the JSON output from the axe-audit script.
     *
     * @return array{violations_count: int, passes_count: int, violations_summary: array<int, array{id: string, impact: string, description: string, nodes_count: int}>}|null
     */
    public function parseOutput(string $output): ?array
    {
        $data = json_decode(trim($output), true);

        if (! is_array($data) || ! isset($data['violations_count'])) {
            return null;
        }

        return [
            'violations_count' => (int) $data['violations_count'],
            'passes_count' => (int) $data['passes_count'],
            'violations_summary' => $data['violations_summary'] ?? [],
        ];
    }

    /**
     * Run the axe-audit node script.
     *
     * @return array{0: bool, 1: string, 2: string} [success, stdout, stderr]
     */
    protected function runProcess(string $domain): array
    {
        $scriptPath = base_path('scripts/axe-audit.mjs');

        $process = new Process(['node', $scriptPath, "https://{$domain}"]);
        $process->setTimeout(60);
        $process->run();

        return [$process->isSuccessful(), $process->getOutput(), $process->getErrorOutput()];
    }
}
