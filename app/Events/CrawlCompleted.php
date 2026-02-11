<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrawlCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $site_id,
        public readonly float $hype_score,
        public readonly int $ai_mention_count,
        public readonly ?string $screenshot_path = null,
        public readonly ?int $crawl_duration_ms = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'CrawlCompleted';
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
            'hype_score' => $this->hype_score,
            'ai_mention_count' => $this->ai_mention_count,
            'screenshot_path' => $this->screenshot_path,
            'crawl_duration_ms' => $this->crawl_duration_ms,
        ];
    }
}
