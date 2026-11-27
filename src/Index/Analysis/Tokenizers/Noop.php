<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Index\Contracts\Tokenizer;

use function Sigmie\Functions\name_configs;

class Noop implements Tokenizer
{
    public function __construct(
        protected readonly string $name = 'keyword',
        protected ?int $bufferSize = null,
    ) {}

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name, isset($config['buffer_size']) ? (int) $config['buffer_size'] : null);
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
                ...($this->bufferSize === null ? [] : ['buffer_size' => $this->bufferSize]),
            ],
        ];
    }
}
