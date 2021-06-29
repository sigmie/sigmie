<?php

declare(strict_types=1);

namespace Sigmie\Support\Shared;

use Exception;
use Sigmie\Base\Analysis\TokenFilter\Lowercase;
use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\Synonyms;
use Sigmie\Base\Analysis\TokenFilter\Trim;
use Sigmie\Base\Analysis\TokenFilter\Truncate;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Unique;
use Sigmie\Base\Analysis\TokenFilter\Uppercase;
use Sigmie\Base\Contracts\Analysis;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Support\Collection as SupportCollection;
use Sigmie\Support\Contracts\Collection;

use function Sigmie\Helpers\random_letters;

trait Filters
{
    private Collection $filters;

    private int $filterPriority = 1;

    private function initFilters(): void
    {
        $this->filters  = $this->filters ?? new SupportCollection();
    }

    private function ensureFilterNameIsAvailable(string $name)
    {
        if ($this->analysis()->hasFilter($name)) {
            throw new Exception('Char filter name already exists.');
        }
    }

    abstract function analysis(): Analysis;

    public function stemming(array $stemming, null|string $name = null,): static
    {
        $name = $name ?? $this->createFilterName('stemming');

        $this->addFilter(new Stemmer($name, $stemming));

        return $this;
    }

    private function createFilterName(string $name): string
    {
        $suffixed = $name . '_' . random_letters();

        while ($this->analysis()->hasFilter($suffixed)) {
            $suffixed = $name . '_' . random_letters();
        }

        return $suffixed;
    }

    public function stopwords(array $stopwords, null|string $name = null,): static
    {
        $name = $name ?? $this->createFilterName('stopwords');

        $this->addFilter(new Stopwords($name, $stopwords));

        return $this;
    }

    public function lowercase(null|string $language = null, null|string $name = null,): static
    {
        $name = $name ?? $this->createFilterName('lowercase');

        $this->addFilter(new Lowercase($name, ['language' => $language]));

        return $this;
    }

    public function trim(null|string $name = null,): static
    {
        $name = $name ?? $this->createFilterName('trim');

        $this->addFilter(new Trim($name));

        return $this;
    }

    public function truncate(int $length = 10, null|string $name = null,): static
    {
        $name = $name ?? $this->createFilterName('truncate');

        $this->addFilter(new Truncate($name, $length));

        return $this;
    }

    public function unique(bool $onlyOnSamePosition = false, null|string $name = null,): static
    {
        $name = $name ?? $this->createFilterName('unique');

        $this->addFilter(new Unique($name, $onlyOnSamePosition));

        return $this;
    }

    public function uppercase(null|string $name = null,): static
    {
        $name = $name ?? $this->createFilterName('uppercase');

        $this->addFilter(new Uppercase($name));

        return $this;
    }

    private function addFilter(TokenFilter $tokenFilter)
    {
        $this->initFilters();

        $this->ensureFilterNameIsAvailable($tokenFilter->name());

        $this->analysis()->updateFilters([$tokenFilter->name() => $tokenFilter]);

        $tokenFilter->setPriority($this->filterPriority);

        $this->filters->set($tokenFilter->name(), $tokenFilter);

        $this->filterPriority++;
    }

    public function synonyms(array $synonyms, null|string $name = null,): static
    {
        $name = $name ?? $this->createFilterName('synonyms');

        $this->addFilter(new Synonyms($name, $synonyms));

        return $this;
    }

    public function language(Language $language): static
    {
        $this->initFilters();

        $this->language = $language;

        return $this;
    }

    public function filters(): array
    {
        $this->initFilters();

        return $this->filters->toArray();
    }
}
