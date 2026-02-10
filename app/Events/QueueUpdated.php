<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $currently_crawling
     */
    public function __construct(
        public readonly int $queued_count,
        public readonly ?array $currently_crawling = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'QueueUpdated';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('crawl-queue'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'queued_count' => $this->queued_count,
            'currently_crawling' => $this->currently_crawling,
        ];
    }
}
