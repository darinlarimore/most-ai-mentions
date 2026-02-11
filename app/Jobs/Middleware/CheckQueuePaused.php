<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class CheckQueuePaused
{
    public function handle(object $job, Closure $next): void
    {
        if (Cache::get('queue:paused')) {
            $job->release(60);

            return;
        }

        $next($job);
    }
}
