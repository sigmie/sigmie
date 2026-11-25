<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Shared\Name;

use function Sigmie\Functions\name_configs;

class Pattern extends Tokenizer
{
    use Name;

    public function __construct(
        public readonly string $name,
        protected string $pattern,
        protected ?string $flags = null,
        protected int|string|null $group = null,
    ) {}

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name, $config['pattern'] ?? '\W+', $config['flags'] ?? null, $config['group'] ?? null);
    }

    public function toRaw(): array
    {
        return [
            $this->name => array_filter([
                'type' => 'pattern',
                'pattern' => $this->pattern,
                'flags' => $this->flags,
                'group' => $this->group,
            ], fn ($value): bool => $value !== null),
        ];
    }
}
