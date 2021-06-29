<?php

declare(strict_types=1);

namespace Sigmie\English;

use Sigmie\English\Filter\PossessiveStemmer as EnglishPossessiveStemmer;
use Sigmie\English\Filter\Stemmer as EnglishStemmer;
use Sigmie\English\Filter\Stopwords as EnglishStopwords;
use Sigmie\Base\Contracts\Language;
use Sigmie\Support\Collection as SupportCollection;
use Sigmie\Support\Contracts\Collection;

class English implements Language
{
    public function filters(): Collection
    {
        return new SupportCollection([
            new EnglishStopwords(),
            new EnglishPossessiveStemmer,
            new EnglishStemmer
        ]);
    }
}
