<?php

declare(strict_types=1);

namespace Sigmie\Greek;

use Sigmie\Base\Contracts\LanguageBuilder;
use Sigmie\Base\Index\Builder as IndexBuilder;

use Sigmie\Greek\Filter\Lowercase as GreekLowercase;
use Sigmie\Greek\Filter\Stemmer as GreekStemmer;
use Sigmie\Greek\Filter\Stopwords as GreekStopwords;

class Builder extends IndexBuilder implements LanguageBuilder
{
    public function greekStopwords(null|string $name = null): static
    {
        $filter = is_null($name) ? new GreekStopwords() : new GreekStopwords($name);

        $this->addFilter($filter);

        return $this;
    }

    public function greekLowercase(null|string $name = null): static
    {
        $filter = is_null($name) ? new GreekLowercase() : new GreekLowercase($name);

        $this->addFilter($filter);

        return $this;
    }

    public function greekStemmer(null|string $name = null): static
    {
        $filter = is_null($name) ? new GreekStemmer() : new GreekStemmer($name);

        $this->addFilter($filter);

        return $this;
    }
}
