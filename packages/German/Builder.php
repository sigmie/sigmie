<?php

declare(strict_types=1);

namespace Sigmie\German;

use Sigmie\Index\Contracts\LanguageBuilder;
use Sigmie\Index\NewIndex as IndexBuilder;

use Sigmie\German\Filter\LightStemmer as GermanLightStemmer;
use Sigmie\German\Filter\Lowercase as GermanLowercase;
use Sigmie\German\Filter\MinimalStemmer as GermanMinimalStemmer;
use Sigmie\German\Filter\Normalize as GermanNormalize;
use Sigmie\German\Filter\Stemmer as GermanStemmer;
use Sigmie\German\Filter\Stemmer2 as GermanStemmer2;
use Sigmie\German\Filter\Stopwords as GermanStopwords;

class Builder extends IndexBuilder implements LanguageBuilder
{
    public function germanStopwords(null|string $name = null): static
    {
        $filter = is_null($name) ? new GermanStopwords() : new GermanStopwords($name);

        $this->addFilter($filter);

        return $this;
    }

    public function germanNormalize(null|string $name = null): static
    {
        $filter = is_null($name) ? new GermanNormalize() : new GermanNormalize($name);

        $this->addFilter($filter);

        return $this;
    }

    public function germanLowercase(null|string $name = null): static
    {
        $filter = is_null($name) ? new GermanLowercase() : new GermanLowercase($name);

        $this->addFilter($filter);

        return $this;
    }

    public function germanLightStemmer(null|string $name = null): static
    {
        $filter = is_null($name) ? new GermanLightStemmer() : new GermanLightStemmer($name);

        $this->addFilter($filter);

        return $this;
    }

    public function germanStemmer(null|string $name = null): static
    {
        $filter = is_null($name) ? new GermanStemmer() : new GermanStemmer($name);

        $this->addFilter($filter);

        return $this;
    }

    public function germanStemmer2(null|string $name = null): static
    {
        $filter = is_null($name) ? new GermanStemmer2() : new GermanStemmer2($name);

        $this->addFilter($filter);

        return $this;
    }

    public function germanMinimalStemmer(null|string $name = null): static
    {
        $filter = is_null($name) ? new GermanMinimalStemmer() : new GermanMinimalStemmer($name);

        $this->addFilter($filter);

        return $this;
    }
}
