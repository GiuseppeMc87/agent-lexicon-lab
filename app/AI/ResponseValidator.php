<?php

namespace App\AI;

use App\AI\DTO\VocabularyProposal;
use App\AI\Exceptions\ProviderException;
use Illuminate\Support\Facades\Validator;
use JsonException;

class ResponseValidator
{
    /**
     * @return array{message: string, vocabulary: list<VocabularyProposal>}
     */
    public function validate(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ProviderException(
                'malformed_json',
                'The AI provider returned malformed JSON.',
            );
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new ProviderException(
                'invalid_response_shape',
                'The AI provider returned an invalid response object.',
            );
        }

        if (array_diff(array_keys($decoded), ['message', 'vocabulary']) !== []) {
            throw new ProviderException(
                'unexpected_response_fields',
                'The AI provider returned unexpected response fields.',
            );
        }

        if (is_array($decoded['vocabulary'] ?? null)) {
            foreach ($decoded['vocabulary'] as $proposal) {
                if (! is_array($proposal) || array_is_list($proposal)) {
                    throw new ProviderException(
                        'invalid_vocabulary_shape',
                        'The AI provider returned an invalid vocabulary proposal.',
                    );
                }

                if (array_diff(array_keys($proposal), ['symbol', 'meaning', 'description']) !== []) {
                    throw new ProviderException(
                        'unexpected_vocabulary_fields',
                        'The AI provider returned unexpected vocabulary fields.',
                    );
                }
            }
        }

        $validator = Validator::make($decoded, [
            'message' => ['required', 'string', 'max:4000'],
            'vocabulary' => ['present', 'array', 'max:20'],
            'vocabulary.*.symbol' => [
                'required',
                'string',
                'max:64',
                'regex:/\A[a-z][a-z0-9-]*\z/',
                'distinct',
            ],
            'vocabulary.*.meaning' => ['required', 'string', 'max:1000'],
            'vocabulary.*.description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            throw new ProviderException(
                'response_validation_failed',
                'The AI provider response did not match the required schema.',
            );
        }

        $validated = $validator->validated();
        $proposals = array_values(array_map(
            fn (array $proposal): VocabularyProposal => new VocabularyProposal(
                symbol: trim($proposal['symbol']),
                meaning: trim($proposal['meaning']),
                description: isset($proposal['description'])
                    ? trim($proposal['description'])
                    : null,
            ),
            $validated['vocabulary'],
        ));

        return [
            'message' => trim($validated['message']),
            'vocabulary' => $proposals,
        ];
    }
}
