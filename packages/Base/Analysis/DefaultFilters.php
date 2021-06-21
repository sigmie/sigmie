<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\Synonyms;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Contracts\Language;

trait DefaultFilters
{
    protected ?Stopwords $stopwords = null;

    protected ?Synonyms $twoWaySynonyms = null;

    protected ?Synonyms $oneWaySynonyms = null;

    protected ?Synonyms $synonyms = null;

    protected ?Stemmer $stemming = null;

    public function stemming(string $name, array $stemming,): self
    {
        $this->stemming = new Stemmer($name, $stemming);

        return $this;
    }

    public function stopwords(string $name, array $stopwords,): self
    {
        $this->stopwords = new Stopwords($name, $stopwords);

        return $this;
    }

    public function twoWaySynonyms(string $name, array $synonyms,): self
    {
        $this->twoWaySynonyms = new Synonyms($name, $synonyms);

        return $this;
    }

    public function oneWaySynonyms(string $name, array $synonyms): self
    {
        $this->oneWaySynonyms = new Synonyms($name, $synonyms);

        return $this;
    }

    public function language(Language $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function defaultFilters(): array
    {
        $results = [];

        if ($this->stopwords instanceof Stopwords) {
            $this->stopwords->setPriority(1);
            $results[$this->stopwords->name()] = $this->stopwords;
        }

        if ($this->twoWaySynonyms instanceof Synonyms) {
            $this->twoWaySynonyms->setPriority(2);
            $results[$this->twoWaySynonyms->name()] = $this->twoWaySynonyms;
        }

        if ($this->oneWaySynonyms instanceof Synonyms) {
            $this->oneWaySynonyms->setPriority(3);
            $results[$this->oneWaySynonyms->name()] = $this->oneWaySynonyms;
        }

        if ($this->stemming instanceof Stemmer) {
            $this->stemming->setPriority(4);
            $results[$this->stemming->name()] = $this->stemming;
        }

        return $results;
    }
}
