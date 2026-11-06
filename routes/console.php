<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// testmail.app has no webhooks, so new support mail is pulled every minute.
Schedule::command('desk:poll-testmail')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => config('services.inbound_email.driver') === 'testmail');

// Evaluate time-based workflow & escalation rules (e.g. "unresolved for 4 hours → escalate to Manager").
Schedule::command('desk:run-automations')
    ->everyMinute()
    ->withoutOverlapping();
