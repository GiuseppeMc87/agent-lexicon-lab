<?php

namespace App\Console\Commands;

use App\Models\Experiment;
use App\Services\ExperimentDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('experiments:run {experiment? : Experiment ID} {--pending : Dispatch a bounded batch of pending experiments}')]
#[Description('Dispatch one experiment or a bounded batch to the queue')]
class DispatchExperiments extends Command
{
    public function handle(ExperimentDispatcher $dispatcher): int
    {
        $experimentId = $this->argument('experiment');

        if ($experimentId !== null) {
            $experiment = Experiment::query()->find($experimentId);

            if ($experiment === null) {
                $this->error('Experiment not found.');

                return self::FAILURE;
            }

            $dispatched = $dispatcher->dispatch($experiment);
            $this->info($dispatched ? 'Experiment dispatched.' : 'Experiment was already running or completed.');

            return self::SUCCESS;
        }

        if (! $this->option('pending')) {
            $this->error('Provide an experiment ID or use --pending.');

            return self::INVALID;
        }

        $count = $dispatcher->dispatchPending((int) config('agent.scheduled_batch_size'));
        $this->info("Dispatched {$count} pending experiment(s).");

        return self::SUCCESS;
    }
}
