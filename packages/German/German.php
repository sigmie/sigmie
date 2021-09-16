<?php

declare(strict_types=1);

namespace Sigmie\German;

use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\ContractsLightStemmerge;
use Sigmie\Base\Contracts\LanguageBuilder;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\German\Filter\Normalize as GermanNormalize;
use Sigmie\German\Filter\Stemmer as GermanStemmer;
use Sigmie\German\Filter\Stopwords as GermanStopwords;
use Sigmie\German\Filter\Lowercase;

class German implements Language
{
    public function builder(HttpConnection $httpConnection): LanguageBuilder
    {
        return new Builder($httpConnection);
    }
}
