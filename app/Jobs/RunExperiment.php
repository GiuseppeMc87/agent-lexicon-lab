<?php

namespace App\Jobs;

use App\Enums\ExperimentStatus;
use App\Models\Experiment;
use App\Services\ExperimentOrchestrator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class RunExperiment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 960;

    public function __construct(public readonly int $experimentId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('experiment:'.$this->experimentId))
                ->dontRelease()
                ->expireAfter(960),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->experimentId;
    }

    /**
     * Execute the job.
     */
    public function handle(ExperimentOrchestrator $orchestrator): void
    {
        $experiment = Experiment::query()->find($this->experimentId);

        if ($experiment === null) {
            return;
        }

        $orchestrator->run($experiment);
    }

    public function failed(?Throwable $exception): void
    {
        Experiment::query()
            ->whereKey($this->experimentId)
            ->where('status', ExperimentStatus::Running)
            ->update([
                'status' => ExperimentStatus::Failed->value,
                'failed_at' => now(),
                'failure_code' => 'queue_job_failed',
                'failure_message' => 'The queued experiment stopped before it completed.',
                'updated_at' => now(),
            ]);
    }
}
