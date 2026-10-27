<?php

namespace App\Http\Controllers;

use App\Enums\ExperimentStatus;
use App\Http\Requests\UpdateExperimentInstructionsRequest;
use App\Models\Experiment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class UpdateExperimentInstructionsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UpdateExperimentInstructionsRequest $request,
        Experiment $experiment,
    ): RedirectResponse {
        $updated = Experiment::query()
            ->whereKey($experiment->id)
            ->where('status', ExperimentStatus::Pending)
            ->whereNull('queued_at')
            ->update($request->safe()->only([
                'sender_instructions',
                'receiver_instructions',
            ]));

        if ($updated !== 1) {
            throw ValidationException::withMessages([
                'experiment' => 'Instructions can only be changed before the experiment is queued.',
            ]);
        }

        return back()->with('success', 'Agent instructions updated.');
    }
}
