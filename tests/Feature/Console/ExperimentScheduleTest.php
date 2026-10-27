<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ExperimentScheduleTest extends TestCase
{
    public function test_paid_experiment_schedule_is_disabled_by_default(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains($event->command ?? '', 'experiments:run'));

        $this->assertFalse((bool) config('agent.schedule_enabled'));
        $this->assertCount(0, $events);
    }
}
