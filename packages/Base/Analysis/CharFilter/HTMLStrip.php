<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Sigmie\Base\Contracts\CharFilter;

class HTMLStrip implements CharFilter
{
    public static function fromRaw(array $raw)
    {
        return new static;
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => 'pattern_replace',
            ]
        ];
    }
    public function name(): string
    {
        return 'html_strip';
    }
}
