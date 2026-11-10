<?php

namespace App\Jobs;

use App\Enums\ExperimentStatus;
use App\Models\Experiment;
use App\Services\ExperimentOrchestrator;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunExperiment implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 960;

    public function __construct(
        public readonly int $experimentId,
        public readonly int $remainingTurns = 1,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('experiment:'.$this->experimentId))
                ->releaseAfter(30)
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

        if (
            $experiment === null
            || ! in_array($experiment->status, [ExperimentStatus::Pending, ExperimentStatus::Running], true)
        ) {
            return;
        }

        $orchestrator->run($experiment, 1);
        $experiment->refresh();

        if ($experiment->status !== ExperimentStatus::Running) {
            return;
        }

        if ($this->remainingTurns > 1) {
            self::dispatch($experiment->id, $this->remainingTurns - 1)
                ->delay(now()->addSecond());

            return;
        }

        DB::transaction(function () use ($experiment): void {
            $locked = Experiment::query()->lockForUpdate()->findOrFail($experiment->id);

            if ($locked->status !== ExperimentStatus::Running) {
                return;
            }

            $locked->update([
                'status' => ExperimentStatus::Paused,
                'queued_at' => null,
                'lock_version' => $locked->lock_version + 1,
            ]);
        });
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
