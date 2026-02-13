<?php

use Illuminate\Support\Facades\Schedule;

// Forge daemon worker handles queue processing — no scheduler-based worker needed
Schedule::command('app:discover-sites')->everyFourHours();
Schedule::command('app:crawl-sites --limit=2')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('app:send-newsletter')->weeklyOn(2, '10:00')->timezone('America/New_York');
Schedule::command('app:sync-company-lists')->weekly()->withoutOverlapping();
