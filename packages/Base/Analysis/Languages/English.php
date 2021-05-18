<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages;

use Sigmie\Base\Analysis\Languages\English\PossessiveStemmer as EnglishPossessiveStemmer;
use Sigmie\Base\Analysis\Languages\English\Stemmer as EnglishStemmer;
use Sigmie\Base\Analysis\Languages\English\Stopwords as EnglishStopwords;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Contracts\Language;

class English implements Language
{
    public function stopwords(): Stopwords
    {
        return new EnglishStopwords();
    }

    public function stemmers(): array
    {
        return [
            new EnglishPossessiveStemmer,
            new EnglishStemmer
        ];
    }
}
