<?php

namespace App\Http\Controllers;

use App\Http\Requests\PollExperimentRequest;
use App\Http\Resources\ExperimentResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\VocabularyEntryResource;
use App\Http\Resources\VocabularyEventResource;
use App\Models\Experiment;
use Illuminate\Http\JsonResponse;

class ExperimentUpdateController extends Controller
{
    public function __invoke(
        PollExperimentRequest $request,
        Experiment $experiment,
    ): JsonResponse {
        $validated = $request->validated();
        $afterMessageId = (int) ($validated['after_message_id'] ?? 0);
        $afterEventId = (int) ($validated['after_event_id'] ?? 0);

        $messages = $experiment->messages()
            ->where('id', '>', $afterMessageId)
            ->with('vocabularyRevisions.vocabularyEntry')
            ->orderBy('id')
            ->get();
        $events = $experiment->vocabularyEvents()
            ->where('id', '>', $afterEventId)
            ->with(['vocabularyEntry', 'vocabularyRevision'])
            ->orderBy('id')
            ->get();
        $vocabulary = $experiment->vocabularyEntries()
            ->with(['currentRevision', 'revisions' => fn ($query) => $query->orderByDesc('version')])
            ->orderBy('symbol')
            ->get();

        $experiment->refresh()->loadCount(['messages', 'vocabularyEntries']);

        return response()->json([
            'experiment' => ExperimentResource::make($experiment)->resolve($request),
            'messages' => MessageResource::collection($messages)->resolve($request),
            'events' => VocabularyEventResource::collection($events)->resolve($request),
            'vocabulary' => VocabularyEntryResource::collection($vocabulary)->resolve($request),
            'cursor' => [
                'message_id' => (int) ($messages->max('id') ?? $afterMessageId),
                'event_id' => (int) ($events->max('id') ?? $afterEventId),
            ],
        ]);
    }
}
