<?php

use Illuminate\Support\Facades\Schedule;

if (config('agent.schedule_enabled')) {
    $interval = max(1, min(59, (int) config('agent.schedule_interval_minutes')));

    Schedule::command('experiments:run --pending')
        ->cron("*/{$interval} * * * *")
        ->onOneServer()
        ->withoutOverlapping(15);
}
