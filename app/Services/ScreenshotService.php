<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Process\Process;

class ScreenshotService
{
    /** @var int Default viewport width in pixels. */
    private const VIEWPORT_WIDTH = 1280;

    /** @var int Default viewport height in pixels. */
    private const VIEWPORT_HEIGHT = 800;

    /** @var int Max viewport height for annotated screenshots (captures most content without timeout). */
    private const MAX_SCREENSHOT_HEIGHT = 4000;

    /** @var int Timeout in seconds for the headless browser process (includes page load + delay). */
    private const TIMEOUT = 90;

    /**
     * Fetch the fully-rendered HTML of a URL using a real Chrome browser.
     *
     * Waits for DOMContentLoaded plus a short delay so JS-rendered content
     * is included. Uses Chrome's native TLS stack to avoid bot detection.
     *
     * @param  string  $url  The fully-qualified URL to fetch.
     * @return string The rendered page HTML.
     *
     * @throws \Spatie\Browsershot\Exceptions\CouldNotTakeBrowsershot
     */
    public function fetchHtml(string $url): string
    {
        $process = $this->startHtmlFetch($url);

        return $this->collectHtmlResult($process);
    }

    /**
     * Start an asynchronous Chrome process to fetch page HTML.
     *
     * Returns a running Symfony Process that can be awaited later,
     * allowing other I/O (e.g. HTTP metadata collection) to run in parallel.
     */
    public function startHtmlFetch(string $url): Process
    {
        $browsershot = $this->buildHtmlFetchBrowsershot($url);

        $command = $browsershot->createBodyHtmlCommand();

        $fullCommand = \Closure::bind(
            fn (array $cmd): array|string => $this->getFullCommand($cmd),
            $browsershot,
            Browsershot::class,
        )($command);

        $process = is_array($fullCommand)
            ? new Process($fullCommand)
            : Process::fromShellCommandline($fullCommand);

        $process->setTimeout(self::TIMEOUT);
        $process->start();

        return $process;
    }

    /**
     * Wait for a previously started HTML fetch process and return the rendered HTML.
     *
     * @throws \RuntimeException If the browser process fails.
     */
    public function collectHtmlResult(Process $process): string
    {
        $process->wait();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Browser process failed: '.$process->getErrorOutput());
        }

        $rawOutput = rtrim($process->getOutput());
        $data = json_decode($rawOutput, true);

        if (isset($data['exception']) && $data['exception'] !== '') {
            throw new \RuntimeException('Browsershot error: '.$data['exception']);
        }

        return $data['result'] ?? '';
    }

    /**
     * Build a configured Browsershot instance for HTML fetching.
     */
    private function buildHtmlFetchBrowsershot(string $url): Browsershot
    {
        return Browsershot::url($url)
            ->setOption('waitUntil', 'domcontentloaded')
            ->setDelay(3000)
            ->windowSize(self::VIEWPORT_WIDTH, self::VIEWPORT_HEIGHT)
            ->timeout(self::TIMEOUT)
            ->dismissDialogs()
            ->noSandbox()
            ->addChromiumArguments([
                'disable-dev-shm-usage',
                'disable-gpu',
                'disable-accelerated-2d-canvas',
                'disable-extensions',
                'disable-software-rasterizer',
                'disable-features=site-per-process',
                'disable-background-timer-throttling',
                'disable-backgrounding-occluded-windows',
                'disable-renderer-backgrounding',
                'js-flags=--max-old-space-size=128',
            ]);
    }

    /**
     * Capture a screenshot of the given URL and store it on disk.
     *
     * Uses Spatie Browsershot (Puppeteer under the hood) to render the page
     * in a headless Chromium instance and save a full-page screenshot as a
     * JPEG. The image is stored in the configured filesystem under the
     * "screenshots" directory.
     *
     * @param  string  $url  The fully-qualified URL to screenshot.
     * @return string The storage path of the saved screenshot (relative to the disk root).
     *
     * @throws \Spatie\Browsershot\Exceptions\CouldNotTakeBrowsershot
     */
    public function capture(string $url): string
    {
        $filename = 'screenshots/'.Str::slug(parse_url($url, PHP_URL_HOST)).'-'.now()->timestamp.'.jpg';

        return $this->renderAndStore($filename, function () use ($url) {
            return Browsershot::url($url)
                ->setOption('waitUntil', 'domcontentloaded')
                ->setDelay(3000)
                ->dismissDialogs();
        });
    }

    /**
     * Capture a screenshot from an HTML string and store it on disk.
     *
     * Renders the provided HTML in a headless Chromium instance and saves
     * a full-page screenshot. Used for annotated crawl views.
     *
     * @param  string  $html  The HTML content to render.
     * @param  string  $slug  A slug used in the filename (typically the site domain).
     * @return string The storage path of the saved screenshot (relative to the disk root).
     *
     * @throws \Spatie\Browsershot\Exceptions\CouldNotTakeBrowsershot
     */
    public function captureHtml(string $html, string $slug): string
    {
        $filename = 'screenshots/annotated-'.Str::slug($slug).'-'.now()->timestamp.'.jpg';

        return $this->renderAndStore($filename, function () use ($html) {
            return Browsershot::html($html)
                ->setOption('waitUntil', 'domcontentloaded')
                ->setDelay(3000)
                ->dismissDialogs();
        }, height: self::MAX_SCREENSHOT_HEIGHT);
    }

    /**
     * Render a Browsershot instance and store the result on the public disk.
     *
     * @param  string  $filename  The relative filename for the public disk.
     * @param  \Closure(): Browsershot  $factory  A closure that returns a configured Browsershot instance.
     */
    private function renderAndStore(string $filename, \Closure $factory, int $height = self::VIEWPORT_HEIGHT): string
    {
        $tempPath = storage_path('app/private/'.$filename);

        $directory = dirname($tempPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $factory()
            ->windowSize(self::VIEWPORT_WIDTH, $height)
            ->timeout(self::TIMEOUT)
            ->setScreenshotType('jpeg', 80)
            ->noSandbox()
            ->addChromiumArguments([
                'disable-dev-shm-usage',
                'disable-gpu',
                'disable-accelerated-2d-canvas',
                'disable-extensions',
                'disable-software-rasterizer',
                'disable-features=site-per-process',
                'disable-background-timer-throttling',
                'disable-backgrounding-occluded-windows',
                'disable-renderer-backgrounding',
                'js-flags=--max-old-space-size=128',
            ])
            ->save($tempPath);

        Storage::disk('public')->put(
            $filename,
            file_get_contents($tempPath),
        );

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        return $filename;
    }
}
