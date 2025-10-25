<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Index\Contracts\Tokenizer;

class Noop implements Tokenizer
{
    public function __construct(
        protected readonly string $name = 'keyword',
    ) {}

    public static function fromRaw(array $raw): static
    {
        return new static();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => 'keyword',
            ],
        ];
    }
}
