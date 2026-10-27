<?php

namespace Tests\Unit\AI;

use App\AI\DTO\AgentContext;
use App\AI\Exceptions\ProviderException;
use App\AI\VertexAiProvider;
use Illuminate\Support\Facades\Http;
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
    }

    public function test_throws_the_second_failure_when_both_structured_responses_are_malformed(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence($this->endpoint())
            ->push($this->providerResponse('{not-json', 'first-response'))
            ->push($this->providerResponse('{still-not-json', 'second-response'));
        $provider = $this->providerWithCachedAccessToken();

        try {
            $provider->generateSenderResponse($this->context());
            $this->fail('A second malformed response was accepted.');
        } catch (ProviderException $exception) {
            $this->assertSame('malformed_json', $exception->failureCode);
            $this->assertSame(
                'The AI provider returned malformed JSON.',
                $exception->safeMessage,
            );
        }

        Http::assertSentCount(2);
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
    private function providerResponse(string $text, string $responseId): array
    {
        return [
            'responseId' => $responseId,
            'candidates' => [[
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
