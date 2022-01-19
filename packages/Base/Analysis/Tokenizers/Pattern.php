<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Shared\Name;

use function Sigmie\Helpers\name_configs;

class Pattern extends Tokenizer
{
    use Name;

    public function __construct(
        protected string $name,
        protected string $pattern,
        protected null|string $flags = null
    ) {
    }

    public function type(): string
    {
        return 'sigmie_pattern_tokenizer';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        $flags = $config['flags'] ?? null;

        return new static($name, $config['pattern'], $flags);
    }

    public function toRaw(): array
    {
        $res = [
            $this->name => [
                'type' => 'pattern',
                'pattern' => $this->pattern,
            ],
        ];

        if (is_null($this->flags)) {
            return $res;
        }

        $res[$this->name]['flags'] = $this->flags;

        return $res;
    }
}
