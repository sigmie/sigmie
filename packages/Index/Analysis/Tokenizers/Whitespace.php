<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Index\Contracts\Tokenizer;

class Whitespace implements Tokenizer
{
    public function __construct(
        protected readonly string $name = 'whitespace',
    ) {

    }
    public static function fromRaw(array $raw): static
    {
        return new static();
    }

    public function toRaw(): array
    {
        $res = [
            $this->name() => [
                'type' => 'whitespace',
            ],
        ];

        return $res;
    }

    public function name(): string
    {
        return $this->name;
    }
}
