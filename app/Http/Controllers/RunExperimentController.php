<?php

namespace App\Http\Controllers;

use App\AI\Exceptions\ExecutionDisabledException;
use App\Models\Experiment;
use App\Services\ExperimentDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RunExperimentController extends Controller
{
    public function __invoke(
        Experiment $experiment,
        ExperimentDispatcher $dispatcher,
    ): RedirectResponse {
        Gate::authorize('run', $experiment);

        try {
            $dispatched = $dispatcher->dispatch($experiment);
        } catch (ExecutionDisabledException $exception) {
            throw ValidationException::withMessages([
                'experiment' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            $dispatched ? 'success' : 'info',
            $dispatched
                ? 'Experiment queued.'
                : 'Experiment is already running or completed.',
        );
    }
}
