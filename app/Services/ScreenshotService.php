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
    private const TIMEOUT = 30;

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
        $tempPath = storage_path('app/private/'.$filename);

        // Ensure the directory exists
        $directory = dirname($tempPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        Browsershot::url($url)
            ->windowSize(self::VIEWPORT_WIDTH, self::VIEWPORT_HEIGHT)
            ->timeout(self::TIMEOUT)
            ->waitUntilNetworkIdle()
            ->setScreenshotType('jpeg', 80)
            ->dismissDialogs()
            ->noSandbox()
            ->save($tempPath);

        // Move the file to the public disk so it can be served
        Storage::disk('public')->put(
            $filename,
            file_get_contents($tempPath),
        );

        // Clean up the temp file
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        return $filename;
    }
}
