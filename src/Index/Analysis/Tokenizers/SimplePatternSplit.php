<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Shared\Name;

use function Sigmie\Functions\name_configs;

class SimplePatternSplit extends Tokenizer
{
    use Name;

    public function __construct(
        public readonly string $name,
        protected string $pattern
    ) {}

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name, $config['pattern']);
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => 'simple_pattern_split',
                'pattern' => $this->pattern,
            ],
        ];
    }
}
