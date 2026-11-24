<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Index\Contracts\Tokenizer;

use function Sigmie\Functions\name_configs;

class Whitespace implements Tokenizer
{
    public function __construct(
        protected readonly string $name = 'whitespace',
        protected ?int $maxTokenLength = null,
    ) {}

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name, isset($config['max_token_length']) ? (int) $config['max_token_length'] : null);
    }

    public function toRaw(): array
    {
        return [
            $this->name() => [
                'type' => 'whitespace',
                ...($this->maxTokenLength === null ? [] : ['max_token_length' => $this->maxTokenLength]),
            ],
        ];
    }

    public function name(): string
    {
        return $this->name;
    }
}
