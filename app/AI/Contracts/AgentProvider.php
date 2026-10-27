<?php

namespace App\AI\Contracts;

use App\AI\DTO\AgentContext;
use App\AI\DTO\ReceiverResponse;
use App\AI\DTO\SenderResponse;

interface AgentProvider
{
    public function name(): string;

    public function model(): string;

    public function generateSenderResponse(AgentContext $context): SenderResponse;

    public function generateReceiverResponse(AgentContext $context): ReceiverResponse;
}
