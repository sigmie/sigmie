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
        $this->twoWaySynonyms = new TwoWaySynonyms($name, $synonyms);

        return $this;
    }

    public function oneWaySynonyms(string $name, array $synonyms): self
    {
        $this->oneWaySynonyms = new OneWaySynonyms($name, $synonyms);

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
        $results = [];

        if ($this->stopwords instanceof Stopwords) {
            $this->stopwords->setPriority(1);
            $results[] = $this->stopwords;
        }

        if ($this->twoWaySynonyms instanceof TwoWaySynonyms) {
            $this->twoWaySynonyms->setPriority(2);
            $results[] = $this->twoWaySynonyms;
        }

        if ($this->oneWaySynonyms instanceof OneWaySynonyms) {
            $this->oneWaySynonyms->setPriority(3);
            $results[] = $this->oneWaySynonyms;
        }

        if ($this->stemming instanceof Stemmer) {
            $this->stemming->setPriority(4);
            $results[] = $this->stemming;
        }

        return $results;
    }
}
