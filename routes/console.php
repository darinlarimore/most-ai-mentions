<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work --stop-when-empty --tries=3 --timeout=120')->everyMinute()->withoutOverlapping();
Schedule::command('app:discover-sites')->daily()->at('02:00');
Schedule::command('app:crawl-sites --limit=10')->everyMinute()->withoutOverlapping();
