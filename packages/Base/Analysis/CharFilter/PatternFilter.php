<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\Configurable;

class PatternFilter implements CharFilter, Configurable
{
    protected string $name = 'sigmie_pattern_char_filter';

    public function __construct(protected string $pattern, protected string $replacement)
    {
    }

    public static function fromRaw(array $raw)
    {
        return new static($raw['pattern'], $raw['replacement']);
    }

    public function config(): array
    {
        return [
            'type' => 'pattern_replace',
            'pattern' => $this->pattern,
            'replacement' => $this->replacement,
            'class' => static::class
        ];
    }

    public function name(): string
    {
        return $this->name;
    }
}
