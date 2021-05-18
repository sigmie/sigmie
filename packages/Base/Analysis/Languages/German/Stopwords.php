<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages\German;

use Sigmie\Base\Analysis\TokenFilter\Stopwords as TokenFilterStopwords;

class Stopwords extends TokenFilterStopwords
{
    protected string $name = 'german_stop';

    public function __construct()
    {
    }

    public function value(): array
    {
        return ['stopwords' => '_german_'];
    }
}
