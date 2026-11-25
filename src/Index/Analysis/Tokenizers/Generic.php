<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Shared\Name;

use function Sigmie\Functions\name_configs;

class Generic extends Tokenizer
{
    use Name;

    public function __construct(public readonly string $name, protected array $settings) {}

    public static function fromRaw(array $raw): static
    {
        [$name, $settings] = name_configs($raw);

        return new static($name, $settings);
    }

    public function toRaw(): array
    {
        return [$this->name => $this->settings];
    }
}
