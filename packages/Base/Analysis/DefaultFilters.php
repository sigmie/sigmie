<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\Synonyms;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Support\Collection as SupportCollection;
use Sigmie\Support\Contracts\Collection;

use function Sigmie\Helpers\random_letters;

trait DefaultFilters
{
    private Collection $defaultFilters;

    private int $priority = 1;

    private function randomSuffix(string $name): string
    {
        return $name . '_' . random_letters();
    }

    private function initFilters(): void
    {
        $this->defaultFilters  = $this->defaultFilters ?? new SupportCollection();
    }

    public function stemming(array $stemming, null|string $name = null,): self
    {
        $name = $name ?? $this->randomSuffix('stemming');

        $this->addFilter(new Stemmer($name, $stemming));

        return $this;
    }

    public function stopwords(array $stopwords, null|string $name = null,): self
    {
        $name = $name ?? $this->randomSuffix('stopwords');

        $this->addFilter(new Stopwords($name, $stopwords));

        return $this;
    }

    private function addFilter(TokenFilter $tokenFilter)
    {
        $this->initFilters();

        $tokenFilter->setPriority($this->priority);

        $this->defaultFilters->set($tokenFilter->name(), $tokenFilter);

        $this->priority++;
    }

    public function synonyms(array $synonyms, null|string $name = null,): self
    {
        $name = $name ?? $this->randomSuffix('synonyms');

        $this->addFilter(new Synonyms($name, $synonyms));

        return $this;
    }

    public function language(Language $language): self
    {
        $this->initFilters();

        $this->language = $language;

        return $this;
    }

    public function defaultFilters(): array
    {
        $this->initFilters();

        return $this->defaultFilters->toArray();
    }
}
