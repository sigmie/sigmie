<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages;

use Sigmie\Base\Analysis\Languages\German\Stemmer as GermanStemmer;
use Sigmie\Base\Analysis\Languages\German\Stopwords as GermanStopwords;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Contracts\Language;

class German implements Language
{
    public function stopwords(): Stopwords
    {
        return new GermanStopwords;
    }

    public function stemmers(): array
    {
        return [new GermanStemmer];
    }
}
