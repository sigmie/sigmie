<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages;

use Sigmie\Base\Analysis\Languages\German\Stemmer as GermanStemmer;
use Sigmie\Base\Analysis\Languages\German\Stopwords as GermanStopwords;
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
