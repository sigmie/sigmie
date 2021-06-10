<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages;

use Sigmie\Base\Analysis\Languages\Greek\Lowercase as GreekLowercase;
use Sigmie\Base\Analysis\Languages\Greek\Stemmer as GreekStemmer;
use Sigmie\Base\Analysis\Languages\Greek\Stopwords as GreekStopwords;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\TokenFilter;
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
