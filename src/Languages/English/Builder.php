<?php

declare(strict_types=1);

namespace Sigmie\Languages\English;

use Sigmie\Languages\English\Filter\LightStemmer  as EnglishLightStemmer;
use Sigmie\Languages\English\Filter\LovinsStemmer as EnglishLovinsStemmer;
use Sigmie\Languages\English\Filter\Lowercase as EnglishLowercase;
use Sigmie\Languages\English\Filter\MinimalStemmer as EnglishMinimalStemmer;
use Sigmie\Languages\English\Filter\Porter2Stemmer as EnglishPorter2Stemmer;
use Sigmie\Languages\English\Filter\PossessiveStemmer as EnglishPossessiveStemmer;
use Sigmie\Languages\English\Filter\Stemmer as EnglishStemmer;
use Sigmie\Languages\English\Filter\Stopwords as EnglishStopwords;
use Sigmie\Index\Contracts\LanguageBuilder;
use Sigmie\Index\NewIndex as IndexBuilder;

class Builder extends IndexBuilder implements LanguageBuilder
{
    protected string $language = 'english';

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
        $filter = is_null($name) ? new EnglishLightStemmer() : new EnglishLightStemmer($name);

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
