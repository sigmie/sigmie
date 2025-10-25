<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Index\Contracts\Tokenizer;

use function Sigmie\Functions\name_configs;

class NonLetter implements Tokenizer
{
    public function __construct(
        protected readonly string $name,
    ) {}

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function toRaw(): array
    {
        return [
            $this->name() => [
                'type' => 'letter',
            ],
        ];
    }
}
