<?php

namespace App\Services;

use App\AI\Exceptions\ExecutionDisabledException;
use App\Enums\ExperimentStatus;
use App\Jobs\RunExperiment;
use App\Models\Experiment;
use Illuminate\Support\Facades\DB;

class ExperimentDispatcher
{
    public function dispatch(Experiment $experiment): bool
    {
        $this->assertExecutionEnabled($experiment);

        return DB::transaction(function () use ($experiment): bool {
            $locked = Experiment::query()->lockForUpdate()->findOrFail($experiment->id);

            if (in_array($locked->status, [ExperimentStatus::Running, ExperimentStatus::Completed], true)) {
                return false;
            }

            $locked->update([
                'status' => ExperimentStatus::Pending,
                'queued_at' => now(),
                'failed_at' => null,
                'failure_code' => null,
                'failure_message' => null,
                'lock_version' => $locked->lock_version + 1,
            ]);

            RunExperiment::dispatch($locked->id)->afterCommit();

            return true;
        });
    }

    public function dispatchPending(int $limit): int
    {
        $dispatched = 0;

        Experiment::query()
            ->where('status', ExperimentStatus::Pending)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Experiment $experiment) use (&$dispatched): void {
                if ($this->dispatch($experiment)) {
                    $dispatched++;
                }
            });

        return $dispatched;
    }

    private function assertExecutionEnabled(Experiment $experiment): void
    {
        if ($experiment->provider === 'vertex' && ! config('agent.paid_execution_enabled')) {
            throw new ExecutionDisabledException;
        }
    }
}
