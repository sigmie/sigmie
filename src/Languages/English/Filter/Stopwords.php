<?php

declare(strict_types=1);

namespace Sigmie\Languages\English\Filter;

use Sigmie\Index\Analysis\TokenFilter\Stopwords as TokenFilterStopwords;

class Stopwords extends TokenFilterStopwords
{
    public function __construct(string $name = 'english_stopwords')
    {
        parent::__construct($name, '_english_');
    }
}
