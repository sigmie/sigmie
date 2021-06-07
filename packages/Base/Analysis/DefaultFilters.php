<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Contracts\Language;

trait DefaultFilters
{
    protected ?Stopwords $stopwords = null;

    protected ?TwoWaySynonyms $twoWaySynonyms = null;

    protected ?OneWaySynonyms $oneWaySynonyms = null;

    protected ?Stemmer $stemming = null;

    public function stemming(array $stemming): self
    {
        $this->stemming = new Stemmer($this->getPrefix() . '_stemmer', $stemming);

        return $this;
    }

    public function stopwords(array $stopwords): self
    {
        $this->stopwords = new Stopwords($this->getPrefix(). '_stopwords', $stopwords);

        return $this;
    }

    public function twoWaySynonyms(array $synonyms): self
    {
        $this->twoWaySynonyms = new TwoWaySynonyms($this->getPrefix() . '_two_way_synonyms', $synonyms);

        return $this;
    }

    public function oneWaySynonyms(array $synonyms): self
    {
        $this->oneWaySynonyms = new OneWaySynonyms($this->getPrefix(). '_one_way_synonyms', $synonyms);

        return $this;
    }

    public function language(Language $language): self
    {
        $this->language = $language;

        return $this;
    }

    abstract protected function getPrefix(): string;

    public function defaultFilters(): array
    {
        $results = [
            new Stopwords($this->getPrefix() . '_stopwords', [], 1),
            new TwoWaySynonyms($this->getPrefix() . '_two_way_synonyms', [], 2),
            new OneWaySynonyms($this->getPrefix() . '_one_way_synonyms', [], 3),
            new Stemmer($this->getPrefix() . '_stemmer', [], 4),
        ];

        if (!is_null($this->stopwords)) {
            $this->stopwords->setPriority(1);
            $results[0] = $this->stopwords;
        }

        if (!is_null($this->twoWaySynonyms)) {
            $this->twoWaySynonyms->setPriority(2);
            $results[1] = $this->twoWaySynonyms;
        }

        if (!is_null($this->oneWaySynonyms)) {
            $this->oneWaySynonyms->setPriority(3);
            $results[2] = $this->oneWaySynonyms;
        }

        if (!is_null($this->stemming)) {
            $this->stemming->setPriority(4);
            $results[3] = $this->stemming;
        }

        return $results;
    }
}
