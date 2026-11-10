<?php

namespace Tests\Unit\AI;

use App\AI\DTO\AgentContext;
use App\AI\Exceptions\ProviderException;
use App\AI\VertexAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use ReflectionProperty;
use Tests\TestCase;

class VertexAiProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agent.vertex.project_id' => 'test-project',
            'agent.vertex.location' => 'us-central1',
            'agent.vertex.model' => 'gemini-test',
            'agent.vertex.connect_timeout_seconds' => 1,
            'agent.vertex.timeout_seconds' => 1,
            'agent.vertex.maximum_output_tokens' => 2048,
            'agent.vertex.temperature' => 0.4,
        ]);
    }

    public function test_returns_the_second_response_when_the_first_response_is_malformed(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence($this->endpoint())
            ->push($this->providerResponse('{not-json', 'first-response'))
            ->push($this->providerResponse(json_encode([
                'message' => 'A valid retry.',
                'vocabulary' => [],
            ], JSON_THROW_ON_ERROR), 'second-response'));
        $provider = $this->providerWithCachedAccessToken();

        $response = $provider->generateSenderResponse($this->context());

        $this->assertSame('A valid retry.', $response->message);
        $this->assertSame('second-response', $response->requestId);
        Http::assertSentCount(2);
        $requests = Http::recorded();
        $this->assertSame(0.4, $requests[0][0]->data()['generationConfig']['temperature']);
        $this->assertSame(2048, $requests[0][0]->data()['generationConfig']['maxOutputTokens']);
        $this->assertSame(0.0, $requests[1][0]->data()['generationConfig']['temperature']);
        $this->assertSame(4096, $requests[1][0]->data()['generationConfig']['maxOutputTokens']);
        $this->assertStringContainsString(
            'Vocabulary may contain at most 20 unique objects',
            $requests[1][0]->data()['contents'][0]['parts'][0]['text'],
        );
        $this->assertStringContainsString(
            'Each meaning and description must contain no more than 1000 characters',
            $requests[1][0]->data()['contents'][0]['parts'][0]['text'],
        );
    }

    public function test_constrains_the_provider_schema_to_twenty_vocabulary_proposals(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence($this->endpoint())
            ->push($this->providerResponse(json_encode([
                'message' => 'A valid response.',
                'vocabulary' => [],
            ], JSON_THROW_ON_ERROR), 'valid-response'));
        $provider = $this->providerWithCachedAccessToken();

        $provider->generateSenderResponse($this->context());

        Http::assertSentCount(1);
        $request = Http::recorded()[0][0];
        $schema = $request->data()['generationConfig']['responseSchema'];
        $this->assertSame(20, $schema['properties']['vocabulary']['maxItems']);
        $this->assertSame(['message', 'vocabulary'], $schema['propertyOrdering']);
        $this->assertSame(
            ['symbol', 'meaning', 'description'],
            $schema['properties']['vocabulary']['items']['propertyOrdering'],
        );
    }

    public function test_throws_after_all_structured_response_attempts_are_malformed(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence($this->endpoint())
            ->push($this->providerResponse('{not-json', 'first-response'))
            ->push($this->providerResponse('{still-not-json', 'second-response'))
            ->push($this->providerResponse('{also-not-json', 'third-response'))
            ->push($this->providerResponse('{finally-not-json', 'fourth-response'));
        $provider = $this->providerWithCachedAccessToken();

        try {
            $provider->generateSenderResponse($this->context());
            $this->fail('An exhausted malformed response was accepted.');
        } catch (ProviderException $exception) {
            $this->assertSame('malformed_json', $exception->failureCode);
            $this->assertSame(
                'The AI provider returned malformed JSON.',
                $exception->safeMessage,
            );
        }

        Http::assertSentCount(4);
        $requests = Http::recorded();
        $this->assertSame(8192, $requests[2][0]->data()['generationConfig']['maxOutputTokens']);
        $this->assertSame(16384, $requests[3][0]->data()['generationConfig']['maxOutputTokens']);
    }

    public function test_throws_a_specific_failure_after_all_structured_responses_are_truncated(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence($this->endpoint())
            ->push($this->providerResponse('{"message":"first', 'first-response', 'MAX_TOKENS'))
            ->push($this->providerResponse('{"message":"second', 'second-response', 'MAX_TOKENS'))
            ->push($this->providerResponse('{"message":"third', 'third-response', 'MAX_TOKENS'))
            ->push($this->providerResponse('{"message":"fourth', 'fourth-response', 'MAX_TOKENS'));
        $provider = $this->providerWithCachedAccessToken();

        try {
            $provider->generateSenderResponse($this->context());
            $this->fail('An exhausted truncated response was accepted.');
        } catch (ProviderException $exception) {
            $this->assertSame('provider_output_truncated', $exception->failureCode);
            $this->assertSame(
                'The AI provider response was truncated before the JSON object was complete.',
                $exception->safeMessage,
            );
        }

        Http::assertSentCount(4);
    }

    public function test_does_not_retry_an_authentication_failure(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence($this->endpoint())
            ->push(['error' => ['message' => 'Unauthenticated']], 401);
        $provider = $this->providerWithCachedAccessToken();

        try {
            $provider->generateSenderResponse($this->context());
            $this->fail('An authentication failure was accepted.');
        } catch (ProviderException $exception) {
            $this->assertSame('provider_authentication_failed', $exception->failureCode);
        }

        Http::assertSentCount(1);
    }

    public function test_retries_rate_limits_with_backoff_until_a_request_succeeds(): void
    {
        Http::preventStrayRequests();
        Sleep::fake();
        Http::fakeSequence($this->endpoint())
            ->push(['error' => ['message' => 'Rate limited']], 429)
            ->push(
                ['error' => ['message' => 'Rate limited']],
                429,
                ['Retry-After' => '7'],
            )
            ->push($this->providerResponse(json_encode([
                'message' => 'Recovered after backoff.',
                'vocabulary' => [],
            ], JSON_THROW_ON_ERROR), 'rate-limit-recovery'));
        $provider = $this->providerWithCachedAccessToken();

        $response = $provider->generateSenderResponse($this->context());

        $this->assertSame('Recovered after backoff.', $response->message);
        Http::assertSentCount(3);
        Sleep::assertSequence([
            Sleep::for(2)->seconds(),
            Sleep::for(7)->seconds(),
        ]);
    }

    public function test_throws_after_the_rate_limit_retry_budget_is_exhausted(): void
    {
        Http::preventStrayRequests();
        Sleep::fake();
        Http::fakeSequence($this->endpoint())
            ->push(['error' => ['message' => 'Rate limited']], 429)
            ->push(['error' => ['message' => 'Rate limited']], 429)
            ->push(['error' => ['message' => 'Rate limited']], 429)
            ->push(['error' => ['message' => 'Rate limited']], 429);
        $provider = $this->providerWithCachedAccessToken();

        try {
            $provider->generateSenderResponse($this->context());
            $this->fail('An exhausted rate-limit response was accepted.');
        } catch (ProviderException $exception) {
            $this->assertSame('provider_rate_limited', $exception->failureCode);
        }

        Http::assertSentCount(4);
        Sleep::assertSequence([
            Sleep::for(2)->seconds(),
            Sleep::for(5)->seconds(),
            Sleep::for(10)->seconds(),
        ]);
    }

    private function providerWithCachedAccessToken(): VertexAiProvider
    {
        $provider = app(VertexAiProvider::class);
        $property = new ReflectionProperty(VertexAiProvider::class, 'accessToken');
        $property->setValue($provider, [
            'token' => 'test-access-token',
            'expires_at' => PHP_INT_MAX,
        ]);

        return $provider;
    }

    private function context(): AgentContext
    {
        return new AgentContext(
            experimentName: 'Structured response retry',
            turn: 1,
            maximumTurns: 2,
            messages: [],
            vocabulary: [],
        );
    }

    /** @return array<string, mixed> */
    private function providerResponse(
        string $text,
        string $responseId,
        string $finishReason = 'STOP',
    ): array {
        return [
            'responseId' => $responseId,
            'candidates' => [[
                'finishReason' => $finishReason,
                'content' => [
                    'parts' => [['text' => $text]],
                ],
            ]],
        ];
    }

    private function endpoint(): string
    {
        return 'https://us-central1-aiplatform.googleapis.com/v1/projects/'
            .'test-project/locations/us-central1/publishers/google/models/'
            .'gemini-test:generateContent';
    }
}
