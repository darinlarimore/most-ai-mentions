<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrawlStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $site_id,
        public readonly string $site_url,
        public readonly ?string $site_name,
        public readonly string $site_slug,
        public readonly ?string $site_source = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'CrawlStarted';
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
            'site_url' => $this->site_url,
            'site_name' => $this->site_name,
            'site_slug' => $this->site_slug,
            'site_source' => $this->site_source,
        ];
    }
}
