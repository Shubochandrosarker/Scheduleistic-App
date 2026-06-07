<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The heartbeat of the publishing engine: dispatch due posts every minute.
Schedule::command('posts:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping();
