<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

class ScreenshotService
{
    /** @var int Default viewport width in pixels. */
    private const VIEWPORT_WIDTH = 1280;

    /** @var int Default viewport height in pixels. */
    private const VIEWPORT_HEIGHT = 800;

    /** @var int Timeout in seconds for the headless browser to load the page. */
    private const TIMEOUT = 45;

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
        return Browsershot::url($url)
            ->setOption('waitUntil', 'domcontentloaded')
            ->setDelay(2000)
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
            ])
            ->bodyHtml();
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
                ->setOption('waitUntil', 'load')
                ->setDelay(2000)
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
                ->setOption('waitUntil', 'networkidle0')
                ->setDelay(2000)
                ->dismissDialogs()
                ->fullPage();
        });
    }

    /**
     * Render a Browsershot instance and store the result on the public disk.
     *
     * @param  string  $filename  The relative filename for the public disk.
     * @param  \Closure(): Browsershot  $factory  A closure that returns a configured Browsershot instance.
     */
    private function renderAndStore(string $filename, \Closure $factory): string
    {
        $tempPath = storage_path('app/private/'.$filename);

        $directory = dirname($tempPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $factory()
            ->windowSize(self::VIEWPORT_WIDTH, self::VIEWPORT_HEIGHT)
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
