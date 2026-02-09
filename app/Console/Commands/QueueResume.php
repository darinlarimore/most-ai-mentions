<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class QueueResume extends Command
{
    protected $signature = 'queue:resume';

    protected $description = 'Resume queue job processing';

    public function handle(): int
    {
        Cache::forget('queue:paused');

        $this->info('Queue processing resumed.');

        return self::SUCCESS;
    }
}
