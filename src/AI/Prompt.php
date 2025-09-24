<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Sigmie\Enums\MessageRole;

class Prompt
{
    public function __construct(protected array $messages = []) {}

    public function system(string $instruction): self
    {
        $this->messages[] = [
            'role' => MessageRole::System,
            'content' => $instruction,
        ];

        return $this;
    }

    public function user(string $instruction): self
    {
        $this->messages[] = [
            'role' => MessageRole::User,
            'content' => $instruction,
        ];

        return $this;
    }

    public function developer(string $instruction): self
    {
        $this->messages[] = [
            'role' => MessageRole::Developer,
            'content' => $instruction,
        ];

        return $this;
    }

    public function assistant(string $instruction): self
    {
        $this->messages[] = [
            'role' => MessageRole::Assistant,
            'content' => $instruction,
        ];

        return $this;
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
