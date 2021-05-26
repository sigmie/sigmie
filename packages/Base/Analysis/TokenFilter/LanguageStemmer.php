<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Contracts\TokenFilter;

abstract class LanguageStemmer implements TokenFilter
{
    public function type(): string
    {
        return 'stemmer';
    }

    public function name(): string
    {
        return $this->name;
    }

    abstract function language(): string;

    public function value(): array
    {
        return [
            'language' => $this->language(),
            'class' => static::class
        ];
    }
}
