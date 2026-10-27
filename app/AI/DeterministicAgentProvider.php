<?php

namespace App\AI;

use App\AI\Contracts\AgentProvider;
use App\AI\DTO\AgentContext;
use App\AI\DTO\ReceiverResponse;
use App\AI\DTO\SenderResponse;
use App\AI\DTO\VocabularyProposal;

class DeterministicAgentProvider implements AgentProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return (string) config('agent.fake.model');
    }

    public function generateSenderResponse(AgentContext $context): SenderResponse
    {
        $symbol = 'signal-'.$context->turn;

        return new SenderResponse(
            message: "Sender turn {$context->turn} introduces [[$symbol]].",
            vocabulary: [new VocabularyProposal(
                symbol: $symbol,
                meaning: "sender meaning for turn {$context->turn}",
                description: 'Deterministic proposal used for local development and tests.',
            )],
            requestId: 'fake-sender-'.$context->turn,
        );
    }

    public function generateReceiverResponse(AgentContext $context): ReceiverResponse
    {
        $symbol = 'signal-'.$context->turn;

        return new ReceiverResponse(
            message: "Receiver turn {$context->turn} answers with [[$symbol]].",
            vocabulary: [new VocabularyProposal(
                symbol: $symbol,
                meaning: "receiver interpretation for turn {$context->turn}",
                description: 'Deterministic inferred meaning used for local development and tests.',
            )],
            requestId: 'fake-receiver-'.$context->turn,
        );
    }
}
