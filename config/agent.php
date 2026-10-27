<?php

return [
    'driver' => env('AI_PROVIDER', 'fake'),
    'paid_execution_enabled' => (bool) env('AI_PAID_EXECUTION_ENABLED', false),
    'schedule_enabled' => (bool) env('AI_SCHEDULE_ENABLED', false),
    'schedule_interval_minutes' => (int) env('AI_SCHEDULE_INTERVAL_MINUTES', 5),
    'scheduled_batch_size' => (int) env('AI_SCHEDULE_BATCH_SIZE', 3),
    'default_turns' => (int) env('AI_DEFAULT_TURNS', 3),
    'maximum_turns' => (int) env('AI_MAXIMUM_TURNS', 8),

    'default_instructions' => [
        'sender' => 'Create the next concise message. You may introduce or revise symbols when that makes the exchange more expressive.',
        'receiver' => 'Interpret the sender message using only the supplied history and known vocabulary, then write a concise reply. Propose your inferred definition for any unfamiliar symbol.',
    ],

    'fake' => [
        'model' => 'deterministic-local',
    ],

    'vertex' => [
        'project_id' => env('GOOGLE_CLOUD_PROJECT'),
        'location' => env('VERTEX_AI_LOCATION', 'us-central1'),
        'model' => env('VERTEX_AI_MODEL', 'gemini-2.5-flash'),
        'timeout_seconds' => (int) env('VERTEX_AI_TIMEOUT_SECONDS', 60),
        'connect_timeout_seconds' => (int) env('VERTEX_AI_CONNECT_TIMEOUT_SECONDS', 5),
        'maximum_output_tokens' => (int) env('VERTEX_AI_MAXIMUM_OUTPUT_TOKENS', 2048),
        'temperature' => (float) env('VERTEX_AI_TEMPERATURE', 0.4),
    ],
];
