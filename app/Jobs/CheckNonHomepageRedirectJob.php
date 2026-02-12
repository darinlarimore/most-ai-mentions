<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\HttpMetadataCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CheckNonHomepageRedirectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly Site $site) {}

    public function handle(): void
    {
        $finalUrl = $this->resolveFinalUrl($this->site->url);

        if (! $finalUrl || ! HttpMetadataCollector::isNonHomepageRedirect($this->site->url, $finalUrl)) {
            return;
        }

        Log::info("Non-homepage redirect detected for {$this->site->url} → {$finalUrl} — removing site");

        $rawScreenshotPath = $this->site->getRawOriginal('screenshot_path');
        if ($rawScreenshotPath && Storage::disk('public')->exists($rawScreenshotPath)) {
            Storage::disk('public')->delete($rawScreenshotPath);
        }

        $this->site->delete();
    }

    /**
     * Follow redirects manually to determine the final URL.
     */
    private function resolveFinalUrl(string $url): ?string
    {
        $currentUrl = $url;

        for ($i = 0; $i < 10; $i++) {
            $response = Http::withOptions([
                'allow_redirects' => false,
                'timeout' => 15,
                'connect_timeout' => 5,
                'verify' => true,
            ])->get($currentUrl);

            $status = $response->status();

            if ($status >= 300 && $status < 400) {
                $location = $response->header('Location');

                if (! $location) {
                    break;
                }

                if (! str_starts_with($location, 'http')) {
                    $parsed = parse_url($currentUrl);
                    $location = $parsed['scheme'].'://'.$parsed['host'].$location;
                }

                $currentUrl = $location;

                continue;
            }

            break;
        }

        return $currentUrl;
    }
}
