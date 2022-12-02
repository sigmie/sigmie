<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Index\Contracts\Tokenizer;

use function Sigmie\Functions\name_configs;

class Ngram implements Tokenizer
{
    public function __construct(
        protected readonly string $name,
        protected string|int $minGram = 3,
        protected string|int $maxGram = 3,
        protected array $tokenChars = ['letter'],
    ) {
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name, $config['min_gram'], $config['max_gram']);
    }

    public function toRaw(): array
    {
        $res = [
            $this->name() => [
                'type' => 'ngram',
                'min_gram' => $this->minGram,
                'max_gram' => $this->maxGram,
                "token_chars" => $this->tokenChars
            ],
        ];

        return $res;
    }

    public function name(): string
    {
        return $this->name;
    }
}
