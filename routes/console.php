<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Application Schedule
|--------------------------------------------------------------------------
*/

// Every Minute: Activate scheduled campaigns right on time
Schedule::command('campaigns:activate')->everyMinute()->withoutOverlapping();

// 1:00 AM: Refund companies for any expired, unclaimed links/codes
Schedule::command('campaigns:clawback')->dailyAt('01:00')->withoutOverlapping();

// 2:00 AM: Nuke abandoned images from the landing page builder
Schedule::command('landing-pages:clean-orphans')->dailyAt('02:00')->withoutOverlapping();

// 3:00 AM: Remove points from user wallets that have passed their expiration date
Schedule::command('points:expire')->dailyAt('03:00')->withoutOverlapping();

// 9:00 AM: Scan campaigns and send out scheduled reminder emails for unclaimed rewards
Schedule::command('campaigns:send-reminders')->dailyAt('09:00')->withoutOverlapping();
