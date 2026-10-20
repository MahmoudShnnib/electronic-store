<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs `php artisan schedule:run` every minute on the server (cron) to run at all.
Schedule::command('orders:cancel-unconfirmed')->hourly()->withoutOverlapping();
