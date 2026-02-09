<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:discover-sites')->daily()->at('02:00');
Schedule::command('app:crawl-sites --limit=25')->hourly();
