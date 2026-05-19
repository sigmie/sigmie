<?php

/**
 * Minimal stubs for Laravel AI SDK interfaces/classes.
 * Loaded only when laravel/ai is not installed, so SigmieIndexTool can be tested.
 */

namespace Illuminate\Contracts\JsonSchema {
    if (! interface_exists(JsonSchema::class)) {
        interface JsonSchema
        {
            public function string(): mixed;

            public function integer(): mixed;
        }
    }
}

namespace Laravel\Ai\Contracts {
    if (! interface_exists(Tool::class)) {
        interface Tool
        {
            public function description(): \Stringable|string;

            public function handle(\Laravel\Ai\Tools\Request $request): \Stringable|string;

            public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array;
        }
    }
}

namespace Laravel\Ai\Tools {
    if (! class_exists(Request::class)) {
        class Request implements \ArrayAccess
        {
            public function __construct(protected array $arguments = []) {}

            public function string(string $key, string $default = ''): string
            {
                return (string) ($this->arguments[$key] ?? $default);
            }

            public function integer(string $key, int $default = 0): int
            {
                return (int) ($this->arguments[$key] ?? $default);
            }

            public function offsetExists(mixed $offset): bool
            {
                return isset($this->arguments[$offset]);
            }

            public function offsetGet(mixed $offset): mixed
            {
                return $this->arguments[$offset] ?? null;
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
                $this->arguments[$offset] = $value;
            }

            public function offsetUnset(mixed $offset): void
            {
                unset($this->arguments[$offset]);
            }
        }
    }
}
