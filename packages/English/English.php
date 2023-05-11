<?php

declare(strict_types=1);

namespace Sigmie\English;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\English\Filter\Lowercase;
use Sigmie\English\Filter\Stemmer;
use Sigmie\English\Filter\Stopwords;
use Sigmie\Index\Contracts\Language;
use Sigmie\Index\Contracts\LanguageBuilder;

class English implements Language
{
    public function builder(ElasticsearchConnection $httpConnection): LanguageBuilder
    {
        return new Builder($httpConnection);
    }

}
