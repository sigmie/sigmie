<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages\English;

use Sigmie\Base\Analysis\TokenFilter\Stopwords as TokenFilterStopwords;

class Stopwords extends TokenFilterStopwords
{
    protected string $name = 'english_stopwords';

    public function __construct()
    {
    }

    public function value(): array
    {
        return [
            'stopwords' => '_english_',
            'class' => static::class
        ];
    }
}
