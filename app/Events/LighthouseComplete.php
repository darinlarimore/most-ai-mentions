<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LighthouseComplete implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $site_id,
        public readonly string $slug,
        public readonly int $performance,
        public readonly int $accessibility,
        public readonly int $best_practices,
        public readonly int $seo,
    ) {}

    public function broadcastAs(): string
    {
        return 'LighthouseComplete';
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
            'performance' => $this->performance,
            'accessibility' => $this->accessibility,
            'best_practices' => $this->best_practices,
            'seo' => $this->seo,
        ];
    }
}
