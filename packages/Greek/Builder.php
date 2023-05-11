<?php

declare(strict_types=1);

namespace Sigmie\Greek;

use Sigmie\Greek\Filter\Lowercase as GreekLowercase;
use Sigmie\Greek\Filter\Stemmer as GreekStemmer;
use Sigmie\Greek\Filter\Stopwords as GreekStopwords;
use Sigmie\Index\Contracts\LanguageBuilder;
use Sigmie\Index\NewIndex as IndexBuilder;

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

    protected function autocompleteTokenFilters(): array
    {
        return [
            new GreekStemmer('autocomplete_greek_stemmer'),
            new GreekStopwords('autocomplete_greek_stopwords'),
            new GreekLowercase('autocomplete_greek_lowercase'),
        ];
    }

    public function greekStemmer(null|string $name = null): static
    {
        $filter = is_null($name) ? new GreekStemmer() : new GreekStemmer($name);

        $this->addFilter($filter);

        return $this;
    }
}
