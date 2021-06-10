<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages;

use Sigmie\Base\Analysis\Languages\English\PossessiveStemmer as EnglishPossessiveStemmer;
use Sigmie\Base\Analysis\Languages\English\Stemmer as EnglishStemmer;
use Sigmie\Base\Analysis\Languages\English\Stopwords as EnglishStopwords;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
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
