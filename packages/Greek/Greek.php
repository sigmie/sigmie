<?php

declare(strict_types=1);

namespace Sigmie\Greek;

use Sigmie\Base\Contracts\Language;
use Sigmie\Greek\Filter\Lowercase as GreekLowercase;
use Sigmie\Greek\Filter\Stemmer as GreekStemmer;
use Sigmie\Greek\Filter\Stopwords as GreekStopwords;
use Sigmie\Support\Collection as SupportCollection;
use Sigmie\Support\Contracts\Collection;

class Greek implements Language
{
    public function filters(): Collection
    {
        return new SupportCollection([
            new GreekStopwords(),
            new GreekStemmer(),
            new GreekLowercase
        ]);
    }
}
