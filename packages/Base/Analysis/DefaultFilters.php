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
    protected Stopwords $stopwords;

    protected TwoWaySynonyms $twoWaySynonyms;

    protected OneWaySynonyms $oneWaySynonyms;

    protected Stemmer $stemming;

    public function stemming(array $stemming): self
    {
        $this->stemming = new Stemmer($this->getPrefix(), $stemming);

        return $this;
    }

    public function stopwords(array $stopwords): self
    {
        $this->stopwords = new Stopwords($this->getPrefix(), $stopwords);

        return $this;
    }

    public function twoWaySynonyms(array $synonyms): self
    {
        $this->twoWaySynonyms = new TwoWaySynonyms($this->getPrefix(), $synonyms);

        return $this;
    }

    public function oneWaySynonyms(array $synonyms): self
    {
        $this->oneWaySynonyms = new OneWaySynonyms($this->getPrefix(), $synonyms);

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
            new Stopwords($this->getPrefix(), [], 1),
            new TwoWaySynonyms($this->getPrefix(), [], 2),
            new OneWaySynonyms($this->getPrefix(), [], 3),
            new Stemmer($this->getPrefix(), [], 4),
        ];

        if (isset($this->stopwords)) {
            $this->stopwords->setPriority(1);
            $results[0] = $this->stopwords;
        }

        if (isset($this->twoWaySynonyms)) {
            $this->twoWaySynonyms->setPriority(2);
            $results[1] = $this->twoWaySynonyms;
        }

        if (isset($this->oneWaySynonyms)) {
            $this->oneWaySynonyms->setPriority(3);
            $results[2] = $this->oneWaySynonyms;
        }

        if (isset($this->stemming)) {
            $this->stemming->setPriority(4);
            $results[3] = $this->stemming;
        }

        return $results;
    }
}
