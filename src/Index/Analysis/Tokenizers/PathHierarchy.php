<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Index\Contracts\Tokenizer;

use function Sigmie\Functions\name_configs;

class PathHierarchy implements Tokenizer
{
    public function __construct(
        protected readonly string $name,
        protected string $delimiter = '/',
        protected bool|string|null $reverse = null,
        protected ?string $replacement = null,
        protected int|string|null $skip = null,
        protected int|string|null $bufferSize = null,
    ) {}

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static(
            $name,
            $config['delimiter'] ?? '/',
            $config['reverse'] ?? null,
            $config['replacement'] ?? null,
            $config['skip'] ?? null,
            $config['buffer_size'] ?? null,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function toRaw(): array
    {
        return [
            $this->name() => array_filter([
                'type' => 'path_hierarchy',
                'delimiter' => $this->delimiter,
                'reverse' => $this->reverse,
                'replacement' => $this->replacement,
                'skip' => $this->skip,
                'buffer_size' => $this->bufferSize,
            ], fn ($value): bool => $value !== null),
        ];
    }
}
