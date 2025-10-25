<?php

declare(strict_types=1);

namespace Sigmie\Languages\Greek;

use Sigmie\Languages\Greek\Filter\Lowercase as GreekLowercase;
use Sigmie\Languages\Greek\Filter\Stemmer as GreekStemmer;
use Sigmie\Languages\Greek\Filter\Stopwords as GreekStopwords;
use Sigmie\Index\Contracts\LanguageBuilder;
use Sigmie\Index\NewIndex as IndexBuilder;

class Builder extends IndexBuilder implements LanguageBuilder
{
    protected string $language = 'greek';

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
