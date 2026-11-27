<?php

declare(strict_types=1);

namespace Sigmie\Languages\Greek\Filter;

use Sigmie\Index\Analysis\TokenFilter\Stopwords as TokenFilterStopwords;

class Stopwords extends TokenFilterStopwords
{
    public function __construct(string $name = 'greek_stopwords')
    {
        parent::__construct($name, '_greek_');
    }
}
