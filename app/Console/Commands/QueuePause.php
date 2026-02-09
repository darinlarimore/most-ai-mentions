<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

class QueuePause extends Command
{
    protected $signature = 'queue:pause {--kill-chrome : Kill any running Chrome/Chromium processes}';

    protected $description = 'Pause queue job processing (jobs stay queued but are not picked up)';

    public function handle(): int
    {
        Cache::put('queue:paused', true);

        $this->info('Queue processing paused. Run `queue:resume` to resume.');

        if ($this->option('kill-chrome')) {
            Process::run('pkill -x "chrome" || true');
            Process::run('pkill -x "chromium" || true');
            Process::run('pkill -x "chromium-browser" || true');
            $this->info('Killed Chrome/Chromium processes.');
        }

        return self::SUCCESS;
    }
}
