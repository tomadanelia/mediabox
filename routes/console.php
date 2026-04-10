<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('app:process-renewals')->dailyAt('19:00');
Schedule::command('app:send-subscription-reminders')->dailyAt('10:00');
