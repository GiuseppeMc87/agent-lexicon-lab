<?php

namespace App\AI;

use App\AI\Contracts\AgentProvider;
use App\AI\DTO\AgentContext;
use App\AI\DTO\ReceiverResponse;
use App\AI\DTO\SenderResponse;
use App\AI\DTO\VocabularyProposal;
use App\AI\Exceptions\ProviderException;
use App\Enums\AgentRole;
use DomainException;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\FetchAuthTokenInterface;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class VertexAiProvider implements AgentProvider
{
    private ?FetchAuthTokenInterface $credentials = null;

    /** @var array{token: string, expires_at: int}|null */
    private ?array $accessToken = null;

    public function __construct(
        private readonly ResponseValidator $responseValidator,
        private readonly VertexPromptFactory $promptFactory,
    ) {}

    public function name(): string
    {
        return 'vertex';
    }

    public function model(): string
    {
        return (string) config('agent.vertex.model');
    }

    public function generateSenderResponse(AgentContext $context): SenderResponse
    {
        $result = $this->generate(AgentRole::Sender, $context);

        return new SenderResponse(
            message: $result['message'],
            vocabulary: $result['vocabulary'],
            requestId: $result['request_id'],
        );
    }

    public function generateReceiverResponse(AgentContext $context): ReceiverResponse
    {
        $result = $this->generate(AgentRole::Receiver, $context);

        return new ReceiverResponse(
            message: $result['message'],
            vocabulary: $result['vocabulary'],
            requestId: $result['request_id'],
        );
    }

    /**
     * @return array{message: string, vocabulary: list<VocabularyProposal>, request_id: string|null}
     */
    private function generate(AgentRole $role, AgentContext $context): array
    {
        $prompt = $this->promptFactory->make($role, $context);

        try {
            $response = $this->sendRequest($prompt);
        } catch (ConnectionException) {
            throw new ProviderException(
                'provider_connection_failed',
                'The AI provider could not be reached. The request was not retried automatically.',
            );
        } catch (ProviderException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ProviderException(
                'provider_request_failed',
                'The AI provider request failed.',
            );
        }

        if (! $response->successful()) {
            $code = match ($response->status()) {
                401, 403 => 'provider_authentication_failed',
                429 => 'provider_rate_limited',
                default => $response->serverError()
                    ? 'provider_unavailable'
                    : 'provider_rejected_request',
            };

            throw new ProviderException($code, 'The AI provider rejected the request.');
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            throw new ProviderException(
                'provider_empty_response',
                'The AI provider returned no structured response.',
            );
        }

        $validated = $this->responseValidator->validate($text);
        $requestId = $response->json('responseId');

        return [
            ...$validated,
            'request_id' => is_string($requestId) ? $requestId : null,
        ];
    }

    /**
     * @param  array{system: string, user: string}  $prompt
     */
    private function sendRequest(array $prompt): Response
    {
        $project = (string) config('agent.vertex.project_id');
        $location = (string) config('agent.vertex.location');
        $model = $this->model();

        if (
            preg_match('/\A[a-z][a-z0-9-]{4,62}\z/', $project) !== 1
            || preg_match('/\A[a-z0-9-]+\z/', $location) !== 1
            || preg_match('/\A[A-Za-z0-9._-]+\z/', $model) !== 1
        ) {
            throw new ProviderException(
                'provider_configuration_invalid',
                'Vertex AI configuration is incomplete or invalid.',
            );
        }

        $url = sprintf(
            'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:generateContent',
            $location,
            $project,
            $location,
            $model,
        );

        $request = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('agent.vertex.connect_timeout_seconds'))
            ->timeout((int) config('agent.vertex.timeout_seconds'));

        $body = [
            'systemInstruction' => [
                'parts' => [['text' => $prompt['system']]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt['user']]],
            ]],
            'generationConfig' => [
                'temperature' => (float) config('agent.vertex.temperature'),
                'maxOutputTokens' => (int) config('agent.vertex.maximum_output_tokens'),
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->responseSchema(),
            ],
        ];

        $response = $request->post($url, $body);

        if ($response->status() === 429) {
            usleep(250_000);

            return $request->post($url, $body);
        }

        return $response;
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null && $this->accessToken['expires_at'] > time()) {
            return $this->accessToken['token'];
        }

        try {
            $credentials = $this->credentials ??= ApplicationDefaultCredentials::getCredentials(
                ['https://www.googleapis.com/auth/cloud-platform'],
                HttpHandlerFactory::build(new Client([
                    'connect_timeout' => (int) config('agent.vertex.connect_timeout_seconds'),
                    'timeout' => (int) config('agent.vertex.timeout_seconds'),
                ])),
            );
            $token = $credentials->fetchAuthToken();
        } catch (DomainException) {
            throw new ProviderException(
                'provider_credentials_missing',
                'Google Application Default Credentials are unavailable.',
            );
        } catch (Throwable) {
            throw new ProviderException(
                'provider_authentication_failed',
                'Google Application Default Credentials could not provide an access token.',
            );
        }

        if (! isset($token['access_token']) || ! is_string($token['access_token'])) {
            throw new ProviderException(
                'provider_authentication_failed',
                'Google Application Default Credentials returned no access token.',
            );
        }

        $this->accessToken = [
            'token' => $token['access_token'],
            'expires_at' => time() + max(60, ((int) ($token['expires_in'] ?? 3600)) - 60),
        ];

        return $this->accessToken['token'];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'required' => ['message', 'vocabulary'],
            'properties' => [
                'message' => ['type' => 'STRING'],
                'vocabulary' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'required' => ['symbol', 'meaning', 'description'],
                        'properties' => [
                            'symbol' => ['type' => 'STRING'],
                            'meaning' => ['type' => 'STRING'],
                            'description' => ['type' => 'STRING', 'nullable' => true],
                        ],
                    ],
                ],
            ],
        ];
    }
}
