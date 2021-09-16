<?php

declare(strict_types=1);

namespace Sigmie\Greek;

use Exception;
use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\LanguageBuilder;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Greek\Filter\Lowercase as GreekLowercase;
use Sigmie\Greek\Filter\Stemmer as GreekStemmer;
use Sigmie\Greek\Filter\Stopwords as GreekStopwords;
use Sigmie\Support\Collection as SupportCollection;
use Sigmie\Support\Contracts\Collection;

class Greek implements Language
{
    public function builder(HttpConnection $httpConnection): LanguageBuilder
    {
        return new Builder($httpConnection);
    }
}
