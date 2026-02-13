<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work --stop-when-empty --tries=3 --timeout=120 --memory=128 --max-jobs=10')->everyMinute()->withoutOverlapping();
Schedule::command('app:discover-sites')->everyFourHours();
Schedule::command('app:crawl-sites --limit=5')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('app:send-newsletter')->weeklyOn(2, '10:00')->timezone('America/New_York');
Schedule::command('app:sync-company-lists')->weekly()->withoutOverlapping();
