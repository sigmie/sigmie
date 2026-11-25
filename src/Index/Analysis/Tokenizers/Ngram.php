<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Index\Contracts\Tokenizer;

use function Sigmie\Functions\analysis_list;
use function Sigmie\Functions\name_configs;

class Ngram implements Tokenizer
{
    public function __construct(
        protected readonly string $name,
        protected string|int $minGram = 3,
        protected string|int $maxGram = 3,
        protected array $tokenChars = ['letter'],
        protected ?string $customTokenChars = null,
    ) {}

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static(
            $name,
            $config['min_gram'] ?? 1,
            $config['max_gram'] ?? 2,
            analysis_list($config['token_chars'] ?? []),
            $config['custom_token_chars'] ?? null,
        );
    }

    public function toRaw(): array
    {
        return [
            $this->name() => [
                'type' => 'ngram',
                'min_gram' => $this->minGram,
                'max_gram' => $this->maxGram,
                'token_chars' => array_values($this->tokenChars),
                ...($this->customTokenChars === null ? [] : ['custom_token_chars' => $this->customTokenChars]),
            ],
        ];
    }

    public function name(): string
    {
        return $this->name;
    }
}
