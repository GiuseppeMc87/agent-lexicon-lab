<?php

namespace App\AI\DTO;

final readonly class VocabularyProposal
{
    public function __construct(
        public string $symbol,
        public string $meaning,
        public ?string $description,
    ) {}
}
