<?php

declare(strict_types=1);

namespace Sigmie\German;

use Sigmie\German\Filter\Stemmer as GermanStemmer;
use Sigmie\German\Filter\Stopwords as GermanStopwords;
use Sigmie\Base\Contracts\Language;
use Sigmie\Support\Collection as SupportCollection;
use Sigmie\Support\Contracts\Collection;

class German implements Language
{
    public function filters(): Collection
    {
        return new SupportCollection([
            new GermanStemmer,
            new GermanStopwords
        ]);
    }
}
