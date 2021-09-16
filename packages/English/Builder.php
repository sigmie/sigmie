<?php

declare(strict_types=1);

namespace Sigmie\English;

use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\LanguageBuilder;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Index\Builder as IndexBuilder;

use Sigmie\English\Filter\PossessiveStemmer as EnglishPossessiveStemmer;
use Sigmie\English\Filter\Stemmer as EnglishStemmer;
use Sigmie\English\Filter\Porter2Stemmer as EnglishPorter2Stemmer;
use Sigmie\English\Filter\MinimalStemmer as EnglishMinimalStemmer;
use Sigmie\English\Filter\LightStemmer  as EnglishLightStemmer;
use Sigmie\English\Filter\LovinsStemmer as EnglishLovinsStemmer;

use Sigmie\English\Filter\Stopwords as EnglishStopwords;
use Sigmie\English\Filter\Lowercase as EnglishLowercase;
use Sigmie\Support\Collection as SupportCollection;
use Sigmie\Support\Contracts\Collection;

class Builder extends IndexBuilder implements LanguageBuilder
{
    public function englishStopwords(null|string $name = null): static
    {
        $filter = is_null($name) ? new EnglishStopwords() : new EnglishStopwords($name);

        $this->addFilter($filter);

        return $this;
    }

    public function englishPossessiveStemming(null|string $name = null): static
    {
        $filter = is_null($name) ? new EnglishPossessiveStemmer() : new EnglishPossessiveStemmer($name);

        $this->addFilter($filter);

        return $this;
    }

    public function englishStemmer(null|string $name = null): static
    {
        $filter = is_null($name) ? new EnglishStemmer() : new EnglishStemmer($name);

        $this->addFilter($filter);

        return $this;
    }

    public function englishPorter2Stemmer(null|string $name = null): static
    {
        $filter = is_null($name) ? new EnglishPorter2Stemmer() : new EnglishPorter2Stemmer($name);

        $this->addFilter($filter);

        return $this;
    }

    public function englishLightStemmer(null|string $name = null)
    {
        $filter = is_null($name) ? new EnglishLightStemmer : new EnglishLightStemmer($name);

        $this->addFilter($filter);

        return $this;
    }

    public function englishLovinsStemmer(null|string $name = null)
    {
        $filter = is_null($name) ? new EnglishLovinsStemmer() : new EnglishLovinsStemmer($name);

        $this->addFilter($filter);

        return $this;
    }

    public function englishMinimalStemmer(null|string $name = null)
    {
        $filter = is_null($name) ? new EnglishMinimalStemmer() : new EnglishMinimalStemmer($name);

        $this->addFilter($filter);

        return $this;
    }

    public function englishLowercase(null|string $name = null): static
    {
        $filter = is_null($name) ? new EnglishLowercase() : new EnglishLowercase($name);

        $this->addFilter($filter);

        return $this;
    }
}
