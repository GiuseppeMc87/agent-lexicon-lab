<?php

namespace App\Http\Controllers;

use App\Enums\ExperimentStatus;
use App\Http\Requests\StoreExperimentRequest;
use App\Http\Resources\ExperimentResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\VocabularyEntryResource;
use App\Http\Resources\VocabularyEventResource;
use App\Models\Experiment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExperimentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Experiment::class);

        $experiments = Experiment::query()
            ->whereBelongsTo($request->user())
            ->withCount(['messages', 'vocabularyEntries'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('experiments/index', [
            'experiments' => ExperimentResource::collection($experiments)
                ->response()
                ->getData(true),
            'limits' => [
                'default_turns' => (int) config('agent.default_turns'),
                'maximum_turns' => (int) config('agent.maximum_turns'),
            ],
            'execution' => [
                'provider' => config('agent.driver'),
                'paid_enabled' => (bool) config('agent.paid_execution_enabled'),
            ],
        ]);
    }

    public function store(StoreExperimentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $provider = (string) config('agent.driver');

        $experiment = $request->user()->experiments()->create([
            'name' => $data['name'],
            'status' => ExperimentStatus::Pending,
            'provider' => $provider,
            'model' => (string) config("agent.{$provider}.model"),
            'max_turns' => $data['max_turns'],
            'current_turn' => 0,
        ]);

        return to_route('experiments.show', $experiment)
            ->with('success', 'Experiment created. Start it when you are ready.');
    }

    public function show(Experiment $experiment): Response
    {
        Gate::authorize('view', $experiment);

        $experiment->loadCount(['messages', 'vocabularyEntries'])->load([
            'messages' => fn ($query) => $query
                ->orderBy('turn')
                ->orderByRaw("CASE role WHEN 'sender' THEN 0 ELSE 1 END"),
            'messages.vocabularyRevisions.vocabularyEntry',
            'vocabularyEntries' => fn ($query) => $query->orderBy('symbol'),
            'vocabularyEntries.currentRevision',
            'vocabularyEntries.revisions' => fn ($query) => $query->orderByDesc('version'),
            'vocabularyEvents' => fn ($query) => $query->orderBy('id'),
            'vocabularyEvents.vocabularyEntry',
            'vocabularyEvents.vocabularyRevision',
        ]);

        return Inertia::render('experiments/show', [
            'experiment' => ExperimentResource::make($experiment)->resolve(request()),
            'messages' => MessageResource::collection($experiment->messages)->resolve(request()),
            'vocabulary' => VocabularyEntryResource::collection($experiment->vocabularyEntries)->resolve(request()),
            'events' => VocabularyEventResource::collection($experiment->vocabularyEvents)->resolve(request()),
            'cursor' => [
                'message_id' => (int) ($experiment->messages->max('id') ?? 0),
                'event_id' => (int) ($experiment->vocabularyEvents->max('id') ?? 0),
            ],
            'poll_interval_ms' => 2500,
        ]);
    }
}
