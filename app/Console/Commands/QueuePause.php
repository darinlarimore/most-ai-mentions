<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class QueuePause extends Command
{
    protected $signature = 'queue:pause';

    protected $description = 'Pause queue job processing (jobs stay queued but are not picked up)';

    public function handle(): int
    {
        Cache::put('queue:paused', true);

        $this->info('Queue processing paused. Run `queue:resume` to resume.');

        return self::SUCCESS;
    }
}
