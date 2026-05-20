<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-deduct monthly server fee on the 30th of every month at midnight
Schedule::command('capital:auto-deduct-server-fee')->monthlyOn(30, '00:00');
