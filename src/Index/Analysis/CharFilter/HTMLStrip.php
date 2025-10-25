<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\CharFilter;

use Sigmie\Index\Contracts\CharFilter;

class HTMLStrip implements CharFilter
{
    public function __construct(
        public string $name = 'html_strip',
    ) {}

    public static function fromRaw(array $raw): static
    {
        return new static;
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => 'html_strip',
            ],
        ];
    }

    public function name(): string
    {
        return $this->name;
    }
}
