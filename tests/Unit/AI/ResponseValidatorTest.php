<?php

namespace Tests\Unit\AI;

use App\AI\Exceptions\ProviderException;
use App\AI\ResponseValidator;
use Tests\TestCase;

class ResponseValidatorTest extends TestCase
{
    public function test_accepts_the_documented_response_contract(): void
    {
        $json = json_encode([
            'message' => 'Follow [[north-signal]].',
            'vocabulary' => [[
                'symbol' => 'north-signal',
                'meaning' => 'the cool horizon marker',
                'description' => 'A navigation concept.',
            ]],
        ], JSON_THROW_ON_ERROR);

        $response = app(ResponseValidator::class)->validate($json);

        $this->assertSame('Follow [[north-signal]].', $response['message']);
        $this->assertSame('north-signal', $response['vocabulary'][0]->symbol);
        $this->assertSame('the cool horizon marker', $response['vocabulary'][0]->meaning);
    }

    public function test_normalizes_a_bracketed_vocabulary_symbol_from_the_provider(): void
    {
        $json = json_encode([
            'message' => 'Follow [[north-signal]].',
            'vocabulary' => [[
                'symbol' => '[[north-signal]]',
                'meaning' => 'the cool horizon marker',
                'description' => null,
            ]],
        ], JSON_THROW_ON_ERROR);

        $response = app(ResponseValidator::class)->validate($json);

        $this->assertSame('north-signal', $response['vocabulary'][0]->symbol);
    }

    public function test_rejects_malformed_json_without_storing_raw_output(): void
    {
        try {
            app(ResponseValidator::class)->validate('{not-json');
            $this->fail('A malformed provider response was accepted.');
        } catch (ProviderException $exception) {
            $this->assertSame('malformed_json', $exception->failureCode);
            $this->assertSame(
                'The AI provider returned malformed JSON.',
                $exception->safeMessage,
            );
            $this->assertStringNotContainsString('{not-json', $exception->getMessage());
        }
    }

    public function test_rejects_source_contract_field_mismatches(): void
    {
        $json = json_encode([
            'message' => 'Reply',
            'inferred_dictionary' => [],
            'message_in_clear' => 'Reply',
        ], JSON_THROW_ON_ERROR);

        try {
            app(ResponseValidator::class)->validate($json);
            $this->fail('Mismatched provider fields were accepted.');
        } catch (ProviderException $exception) {
            $this->assertSame('unexpected_response_fields', $exception->failureCode);
        }
    }

    public function test_rejects_incorrect_field_types(): void
    {
        $json = json_encode([
            'message' => ['not', 'a', 'string'],
            'vocabulary' => 'not-an-array',
        ], JSON_THROW_ON_ERROR);

        try {
            app(ResponseValidator::class)->validate($json);
            $this->fail('Incorrect provider field types were accepted.');
        } catch (ProviderException $exception) {
            $this->assertSame('response_validation_failed', $exception->failureCode);
        }
    }
}
