<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages;

use Sigmie\Base\Analysis\Languages\Greek\Stemmer as GreekStemmer;
use Sigmie\Base\Analysis\Languages\Greek\Stopwords as GreekStopwords;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Contracts\Language;

class Greek implements Language
{

    public function stopwords(): Stopwords
    {
        return new GreekStopwords;
    }

    public function stemmers(): array
    {
        return [new GreekStemmer];
    }
}
