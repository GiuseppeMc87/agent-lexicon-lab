<?php

namespace App\Http\Controllers;

use App\Enums\ExperimentStatus;
use App\Models\Experiment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CloneExperimentController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Experiment $experiment): RedirectResponse
    {
        Gate::authorize('view', $experiment);
        Gate::authorize('create', Experiment::class);

        $provider = (string) config('agent.driver');
        $clone = $request->user()->experiments()->create([
            'name' => Str::limit('Copy of '.$experiment->name, 120, ''),
            'status' => ExperimentStatus::Pending,
            'provider' => $provider,
            'model' => (string) config("agent.{$provider}.model"),
            'sender_instructions' => $experiment->sender_instructions,
            'receiver_instructions' => $experiment->receiver_instructions,
            'max_turns' => $experiment->max_turns,
            'current_turn' => 0,
        ]);

        return to_route('experiments.show', $clone)
            ->with('success', 'Experiment cloned. You can now edit its instructions.');
    }
}
