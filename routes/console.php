<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('appointments:send-reminders')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('notifications:cleanup')
    ->weekly()
    ->sundays()
    ->at('01:00');
