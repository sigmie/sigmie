<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use function Sigmie\Functions\name_configs;
use Sigmie\Shared\Name;

class SimplePattern extends Tokenizer
{
    use Name;

    public function __construct(
        public readonly string $name,
        protected string $pattern,
    ) {
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name, $config['pattern']);
    }

    public function toRaw(): array
    {
        $res = [
            $this->name => [
                'type' => 'simple_pattern',
                'pattern' => $this->pattern,
            ],
        ];

        return $res;
    }
}
