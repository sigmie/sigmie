<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Sigmie\AI\Role;

class Prompt
{
    protected ?NewJsonSchema $jsonSchemaBuilder = null;

    public function __construct(protected array $messages = []) {}

    public function answerJsonSchema(callable $callback): self
    {
        $this->jsonSchemaBuilder = new NewJsonSchema();
        $callback($this->jsonSchemaBuilder);

        return $this;
    }

    public function jsonSchema(): array
    {
        if ($this->jsonSchemaBuilder) {
            return $this->jsonSchemaBuilder->toArray();
        }

        // Default schema
        return [
            'type' => 'object',
            'properties' => [
                'answer' => ['type' => 'string'],
            ],
            'required' => ['answer'],
            'additionalProperties' => false,
        ];
    }

    public function system(string $instruction): self
    {
        $this->messages[] = [
            'role' => Role::System,
            'content' => $instruction,
        ];

        return $this;
    }

    public function user(string $instruction): self
    {
        $this->messages[] = [
            'role' => Role::User,
            'content' => $instruction,
        ];

        return $this;
    }

    public function developer(string $instruction): self
    {
        $this->messages[] = [
            'role' => Role::System,
            'content' => $instruction,
        ];

        return $this;
    }

    public function assistant(string $instruction): self
    {
        $this->messages[] = [
            'role' => Role::Model,
            'content' => $instruction,
        ];

        return $this;
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
