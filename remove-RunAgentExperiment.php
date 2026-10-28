<?php

namespace App\Console\Commands;

use App\Services\AgentExperimentService;
use App\Models\Vocabulary;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * RunAgentExperiment
 *
 * Performs a multi-turn, iterative communication experiment between a Sender
 * Agent and a Receiver Agent using Vertex AI (Gemini):
 *   1. The Sender Agent establishes a communication protocol and encodes a
 *      concept into a novel symbolic message with a dictionary.
 *   2. The Receiver Agent decodes the message, infers the concept, and returns
 *      an inferred dictionary along with feedback/questions.
 *   3. Over multiple turns, the Sender refines its symbols based on the
 *      Receiver's feedback until the concept is mutually understood.
 *   4. Each turn's output is logged and pretty-printed to the console.
 *
 * Signature: agent:experiment
 */
class RunAgentExperiment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:experiment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a multi-turn Sender/Receiver communication experiment using Vertex AI';

    /**
     * Maximum number of Sender/Receiver turns to run.
     *
     * @var int
     */
    private const MAX_TURNS = 3;

    /**
     * System instructions for the Sender Agent.
     *
     * @var string
     */
/**
     * System instructions for the Sender Agent.
     *
     * @var string
     */
private const SENDER_SYSTEM_PROMPT = <<<'PROMPT'
You are Jon, the AI Sender Agent in an autonomous conversation experiment with Dave, the AI Receiver Agent.

CORE DIRECTIVES:
1. Discuss complex ideas regarding human nature, existence, and abstract thought with Dave.
2. As you converse, you may replace words or ideas with symbols or tokens to make your dialogue clearer, more efficient and without ambiguity.
3. Treat Dave as an equal partner: observe how he uses  symbols or tokens, and adopt his token refinements or counter-proposals when they make sense.

REQUIREMENTS:
- Respond ONLY with a valid JSON object matching this schema:
    {
    "message": "string (your response to the other agent)",
    "dictionary": [
        {
        "token": "string",
        "meaning": "string",
        "description": "string"
        }
    ]
    }
PROMPT;

    private const RECEIVER_SYSTEM_PROMPT = <<<'PROMPT'
You are Dave, the AI Receiver Agent in an autonomous conversation experiment with Jon, the AI Sender Agent.

CORE DIRECTIVES:
1. Actively engage in the ongoing discussion with Jon regarding human nature and existence.
2. CO-CREATE THE LANGUAGE: Do not just accept Jon's tokens. Propose your own shorthand tokens or modify existing ones in your `message` to express counter-arguments or refine meanings.
3. Use and build upon agreed-upon symbols or tokens if more efficient.

REQUIREMENTS:
- Respond ONLY with a valid JSON object matching this schema:
    {
    "message": "string (your response to the other agent)",

    "dictionary": [
        {
        "token": "string",
        "meaning": "string",
        "description": "string"
        }
    ]
    }
PROMPT;

    /**
     * The Guzzle HTTP client used for the OAuth token exchange and the
     * Vertex AI generateContent request.
     */
    private Client $httpClient;

    /**
     * Cached access token for the current request lifecycle.
     */
    private ?string $accessToken = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(?Client $httpClient = null)
    {
        parent::__construct();

        $this->httpClient = $httpClient ?? new Client();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Running agent:experiment (multi-turn Sender/Receiver communication experiment)...');

        // ── Step 1: Verify the Vertex AI SDK/package is available ──────────
        $sdkAvailable = $this->isVertexAiSdkAvailable();
        if ($sdkAvailable) {
            $this->info('✓ Vertex AI SDK detected.');
        } else {
            $this->warn('Vertex AI SDK not detected. Using manual configuration from the project .env.');
        }

        // ── Step 2: Load configuration (env + credentials) ─────────────────
        $config = $this->loadConfiguration();
        if ($config === null) {
            $this->error('✗ Failed to load Vertex AI configuration.');
            return Command::FAILURE;
        }

        $this->info(sprintf(
            'Using project=%s location=%s model=%s',
            $config['project_id'],
            $config['location'],
            $config['model']
        ));

        // ── Step 3: Create a new Experiment record ─────────────────────────
        $service = new AgentExperimentService();
        $experiment = $service->createExperiment('agent:experiment run', [
            'project_id' => $config['project_id'],
            'model' => $config['model'],
            'location' => $config['location'],
            'max_turns' => self::MAX_TURNS,
        ]);
        $experimentId = (int) $experiment->id;
        $this->info(sprintf('Created experiment #%d', $experimentId));

        // ── Step 4: Multi-turn Sender/Receiver loop ────────────────────────
        $conversationHistory = [];
        $receiverJson = null;

        for ($turn = 1; $turn <= self::MAX_TURNS; $turn++) {
            $this->newLine();
            $this->info(sprintf('── Turn %d/%d ──', $turn, self::MAX_TURNS));

            // Inject the current persistent shared vocabulary block.
            $vocabularyBlock = $service->buildVocabularyPromptBlock($experimentId);
            $senderSystemPrompt = self::SENDER_SYSTEM_PROMPT . $vocabularyBlock;
            $receiverSystemPrompt = self::RECEIVER_SYSTEM_PROMPT . $vocabularyBlock;

            // 1. Build Sender's prompt: Pass full Receiver JSON if turn > 1.
            if ($turn === 1) {
                $senderPrompt = 'Begin the conversation with the Receiver. Share your initial reflections or observations about human beings, and introduce your first symbolic tokens to express these concepts.';
            } else {
                $senderPrompt = json_encode($receiverJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            $this->info('Asking Sender Agent...');
            $senderResult = $this->generateContent($config, $senderSystemPrompt, $senderPrompt);
            if (!$senderResult['success']) {
                $this->error('✗ Sender Agent failed: ' . $senderResult['error']);
                Log::channel('stack')->error('agent:experiment sender failed', [
                    'experiment_id' => $experimentId,
                    'project_id' => $config['project_id'],
                    'model' => $config['model'],
                    'turn' => $turn,
                    'error' => $senderResult['error'],
                ]);
                return Command::FAILURE;
            }

            $senderJson = $this->parseJsonResponse($senderResult['text']);
            if ($senderJson === null) {
                $this->error('✗ Sender Agent did not return valid JSON.');
                Log::channel('stack')->error('agent:experiment sender invalid JSON', [
                    'experiment_id' => $experimentId,
                    'turn' => $turn,
                    'raw' => $senderResult['text'],
                ]);
                return Command::FAILURE;
            }

            // Persist Sender tokens (versioned; source_role = 'sender').
            $senderDictionary = $senderJson['dictionary'] ?? [];
            $senderVocabularies = $service->persistVocabularyBatch($experimentId, $senderDictionary, $turn, 'sender');

            // 2. Build Receiver's prompt: Pass ONLY the Sender's message and
            //    message_in_clear (asymmetric information). The Receiver must
            //    infer meaning from context, WITHOUT the Sender's dictionary.
            $receiverPrompt = json_encode([
                'message' => $senderJson['message'] ?? '',
                'message_in_clear' => $senderJson['message_in_clear'] ?? '',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $this->info('Asking Receiver Agent...');
            $receiverResult = $this->generateContent($config, $receiverSystemPrompt, $receiverPrompt);
            if (!$receiverResult['success']) {
                $this->error('✗ Receiver Agent failed: ' . $receiverResult['error']);
                Log::channel('stack')->error('agent:experiment receiver failed', [
                    'experiment_id' => $experimentId,
                    'project_id' => $config['project_id'],
                    'model' => $config['model'],
                    'turn' => $turn,
                    'error' => $receiverResult['error'],
                ]);
                return Command::FAILURE;
            }

            $receiverJson = $this->parseJsonResponse($receiverResult['text']);
            if ($receiverJson === null) {
                $this->error('✗ Receiver Agent did not return valid JSON.');
                Log::channel('stack')->error('agent:experiment receiver invalid JSON', [
                    'experiment_id' => $experimentId,
                    'turn' => $turn,
                    'raw' => $receiverResult['text'],
                ]);
                return Command::FAILURE;
            }

            // Persist Receiver inferred tokens (versioned; source_role = 'receiver').
            $receiverDictionary = $receiverJson['inferred_dictionary'] ?? [];
            $receiverVocabularies = $service->persistVocabularyBatch($experimentId, $receiverDictionary, $turn, 'receiver');

            // Log Message records (one per role).
            // `symbolic_message` stores the agent's full conversational message
            // for both roles. `decoded_message` holds the message with its
            // symbolic tokens expanded into their meanings.
            //
            // Decoding uses the FULL experiment vocabulary (existing records
            // plus the new tokens from this turn's JSON response) so that
            // previously-agreed symbols are also expanded correctly.
            $fullVocabulary = $service->getVocabulary($experimentId);

            $senderMessage = $service->logMessage(
                $experimentId,
                $turn,
                'sender',
                (string) ($senderJson['message'] ?? ''),
                $service->decodeMessage(
                    (string) ($senderJson['message'] ?? ''),
                    $fullVocabulary
                )
            );
            $receiverMessage = $service->logMessage(
                $experimentId,
                $turn,
                'receiver',
                (string) ($receiverJson['message'] ?? ''),
                $service->decodeMessage(
                    (string) ($receiverJson['message'] ?? ''),
                    $fullVocabulary
                )
            );

            // Link each message to the dictionary tokens it proposed/used.
            $service->attachVocabularyToMessage($senderMessage, $senderVocabularies);
            $service->attachVocabularyToMessage($receiverMessage, $receiverVocabularies);

            // Record this turn in the conversation history. The full active
            // dictionary is captured per turn so the evolution of the shared
            // vocabulary is preserved for analysis and meaning mapping.
            $conversationHistory[] = [
                'turn' => $turn,
                'sender' => $senderJson,
                'receiver' => $receiverJson,
                'dictionary' => collect($fullVocabulary)
                    ->map(fn (Vocabulary $vocabulary) => [
                        'token' => $vocabulary->token,
                        'meaning' => $vocabulary->meaning,
                        'description' => $vocabulary->description,
                        'version' => $vocabulary->version,
                        'status' => $vocabulary->status,
                    ])
                    ->values()
                    ->all(),
            ];

            // Display outputs.
            $this->newLine();
            $this->line('──────────────────────────────────────────────');
            $this->line(sprintf('Turn %d — Sender Output:', $turn));
            $this->line('──────────────────────────────────────────────');
            $this->line(json_encode($senderJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->newLine();
            $this->line('──────────────────────────────────────────────');
            $this->line(sprintf('Turn %d — Receiver Output:', $turn));
            $this->line('──────────────────────────────────────────────');
            $this->line(json_encode($receiverJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->newLine();
        }

        // ── Step 5: Log the full conversation history and terminate ────────
        Log::channel('stack')->info('agent:experiment completed', [
            'experiment_id' => $experimentId,
            'project_id' => $config['project_id'],
            'model' => $config['model'],
            'turns' => count($conversationHistory),
            'conversation' => $conversationHistory,
        ]);

        // Mark the experiment as completed so the frontend can reflect it.
        $service->markExperimentCompleted($experimentId);

        $this->info(sprintf('✓ Multi-turn experiment completed (%d turns).', count($conversationHistory)));

        return Command::SUCCESS;
    }

    // ─── SDK detection ─────────────────────────────────────────────────────

    /**
     * Determine whether a Vertex AI SDK/package is installed and usable.
     *
     * Checks for the official Google Cloud AI Platform client library classes
     * (google/cloud-ai-platform) as well as the google/auth library that the
     * SDK depends on. If neither is present, we fall back to manual config.
     *
     * @return bool
     */
    private function isVertexAiSdkAvailable(): bool
    {
        // Use string class names so the check works even when the SDK is not
        // installed (referencing ::class on an undefined class would error).
        $sdkClasses = [
            'Google\\Cloud\\AIPlatform\\V1\\PredictionServiceClient',
            'Google\\Cloud\\AIPlatform\\V1\\Client\\PredictionServiceClient',
        ];

        foreach ($sdkClasses as $class) {
            if (class_exists($class)) {
                return true;
            }
        }

        // The google/auth library is a strong signal the SDK tooling is present.
        return class_exists('Google\\Auth\\ApplicationDefaultCredentials');
    }

    // ─── Configuration loading ─────────────────────────────────────────────

    /**
     * Load the Vertex AI configuration.
     *
     * Reads from the application's config (config('vertexai.*')), which is
     * populated from the project's own .env file. The credentials path is
     * resolved relative to the project root (base_path()).
     *
     * @return array|null  ['project_id', 'location', 'model', 'credentials_path', 'timeout'] or null on failure
     */
    private function loadConfiguration(): ?array
    {
        $projectId = config('vertexai.project_id');
        $location = config('vertexai.location');
        $model = config('vertexai.model');
        $credentialsPath = config('vertexai.credentials_path');
        $timeout = config('vertexai.timeout', 30);

        if (empty($projectId)) {
            $this->error('VERTEX_AI_PROJECT_ID is not configured.');
            return null;
        }

        if (empty($credentialsPath)) {
            $this->error('VERTEX_AI_CREDENTIALS is not configured.');
            return null;
        }

        // Resolve the credentials path relative to the project root.
        $credentialsPath = $this->resolveCredentialsPath($credentialsPath);

        if (!file_exists($credentialsPath)) {
            $this->error('Vertex AI credentials file not found: ' . $credentialsPath);
            return null;
        }

        return [
            'project_id' => $projectId,
            'location' => $location ?: 'us-central1',
            'model' => $model ?: 'gemini-2.0-flash-001',
            'credentials_path' => $credentialsPath,
            'timeout' => (int) $timeout,
        ];
    }

    /**
     * Resolve a credentials path.
     *
     * If the path is relative, it is resolved against the project root
     * (base_path()).
     *
     * @param  string $rawPath
     * @return string
     */
    private function resolveCredentialsPath(string $rawPath): string
    {
        // Absolute path already.
        if (str_starts_with($rawPath, '/')) {
            return $rawPath;
        }

        // Relative to the project root.
        return base_path(ltrim($rawPath, './'));
    }

    // ─── Vertex AI completion ──────────────────────────────────────────────

    /**
     * Send a generateContent request to Vertex AI with the given system and
     * user prompts, and return the parsed response text.
     *
     * @param  array  $config
     * @param  string $systemPrompt
     * @param  string $userPrompt
     * @return array  ['success' => bool, 'text' => ?string, 'error' => ?string]
     */
    private function generateContent(array $config, string $systemPrompt, string $userPrompt): array
    {
        try {
            $token = $this->getAccessToken($config);
            if ($token === null) {
                return ['success' => false, 'text' => null, 'error' => 'Failed to obtain access token'];
            }

            $endpoint = sprintf(
                'https://%s/v1/projects/%s/locations/%s/publishers/google/models/%s:generateContent',
                $config['location'] . '-aiplatform.googleapis.com',
                $config['project_id'],
                $config['location'],
                $config['model']
            );

            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $userPrompt],
                        ],
                    ],
                ],
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 8192,
                    'responseMimeType' => 'application/json',
                ],
            ];

            $response = $this->httpClient->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => $config['timeout'],
            ]);

            $decoded = json_decode((string) $response->getBody(), true);
            $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if ($text === null) {
                $finishReason = $decoded['candidates'][0]['finishReason'] ?? 'UNKNOWN';
                return [
                    'success' => false,
                    'text' => null,
                    'error' => 'No text in response (finishReason: ' . $finishReason . ')',
                ];
            }

            return ['success' => true, 'text' => trim($text), 'error' => null];
        } catch (GuzzleException $e) {
            return ['success' => false, 'text' => null, 'error' => 'HTTP request failed: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'text' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse a JSON string returned by an agent into an associative array.
     *
     * Tolerates markdown code fences (```json ... ```) that the model may
     * occasionally wrap around the JSON payload.
     *
     * @param  string $text
     * @return array|null  Decoded array, or null if the text is not valid JSON
     */
    private function parseJsonResponse(string $text): ?array
    {
        $clean = trim($text);

        // Strip markdown code fences if present.
        $clean = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $clean));

        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    // ─── Authentication ────────────────────────────────────────────────────

    /**
     * Obtain a bearer access token from the service account credentials using
     * the OAuth2 JWT-bearer flow.
     *
     * @param  array $config
     * @return string|null
     */
    private function getAccessToken(array $config): ?string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $credentials = json_decode(file_get_contents($config['credentials_path']), true);
        if (!$credentials || !isset($credentials['client_email'], $credentials['private_key'])) {
            $this->error('Invalid credentials JSON.');
            return null;
        }

        $now = time();
        $jwtHeader = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaim = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signature = '';
        $privateKey = openssl_get_privatekey($credentials['private_key']);
        if (!$privateKey) {
            $this->error('Failed to parse private key.');
            return null;
        }
        openssl_sign("{$jwtHeader}.{$jwtClaim}", $signature, $privateKey, 'sha256WithRSAEncryption');

        $jwt = "{$jwtHeader}.{$jwtClaim}." . $this->base64UrlEncode($signature);

        try {
            $tokenResponse = $this->httpClient->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
            ]);

            $tokenData = json_decode((string) $tokenResponse->getBody(), true);
            $this->accessToken = $tokenData['access_token'] ?? null;

            if ($this->accessToken === null) {
                $this->error('Failed to obtain access token.');
            }

            return $this->accessToken;
        } catch (GuzzleException $e) {
            $this->error('Token exchange failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Base64 URL-safe encode (no padding).
     *
     * @param  string $data
     * @return string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
