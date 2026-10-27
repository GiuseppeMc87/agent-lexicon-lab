<?php

namespace App\AI\DTO;

final readonly class SenderResponse
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
