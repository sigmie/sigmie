<?php

declare(strict_types=1);

namespace Sigmie\Languages\German\Filter;

use Sigmie\Index\Analysis\TokenFilter\Stopwords as TokenFilterStopwords;

class Stopwords extends TokenFilterStopwords
{
    public function __construct(string $name = 'german_stopwords')
    {
        parent::__construct($name, '_german_');
    }
}
