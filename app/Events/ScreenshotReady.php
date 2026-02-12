<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScreenshotReady implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $site_id,
        public readonly string $slug,
        public readonly string $screenshot_path,
    ) {}

    public function broadcastAs(): string
    {
        return 'ScreenshotReady';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('crawl-activity'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'site_id' => $this->site_id,
            'slug' => $this->slug,
            'screenshot_path' => $this->screenshot_path,
        ];
    }
}
