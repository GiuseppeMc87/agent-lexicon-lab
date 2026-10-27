<?php

namespace App\AI\DTO;

final readonly class AgentContext
{
    /**
     * @param  list<array{turn: int, role: string, message: string}>  $messages
     * @param  list<array{symbol: string, meaning: string, description: string|null, version: int}>  $vocabulary
     */
    public function __construct(
        public string $experimentName,
        public int $turn,
        public int $maximumTurns,
        public array $messages,
        public array $vocabulary,
        public ?string $roleInstructions = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'experiment' => $this->experimentName,
            'turn' => $this->turn,
            'maximum_turns' => $this->maximumTurns,
            'messages' => $this->messages,
            'known_vocabulary' => $this->vocabulary,
            'role_instructions' => $this->roleInstructions,
        ];
    }
}
