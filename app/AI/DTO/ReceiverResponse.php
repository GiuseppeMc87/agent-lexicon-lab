<?php

namespace App\AI\DTO;

final readonly class ReceiverResponse
{
    /**
     * @param  list<VocabularyProposal>  $vocabulary
     */
    public function __construct(
        public string $message,
        public array $vocabulary,
        public ?string $requestId = null,
    ) {}
}
