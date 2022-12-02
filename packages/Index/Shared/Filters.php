<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Exception;
use function Sigmie\Functions\random_letters;
use Sigmie\Index\Analysis\TokenFilter\AsciiFolding;
use Sigmie\Index\Analysis\TokenFilter\DecimalDigit;
use Sigmie\Index\Analysis\TokenFilter\Keywords;
use Sigmie\Index\Analysis\TokenFilter\Lowercase;
use Sigmie\Index\Analysis\TokenFilter\Stemmer;
use Sigmie\Index\Analysis\TokenFilter\Stopwords;
use Sigmie\Index\Analysis\TokenFilter\Synonyms;
use Sigmie\Index\Analysis\TokenFilter\TokenLimit;
use Sigmie\Index\Analysis\TokenFilter\Trim;
use Sigmie\Index\Analysis\TokenFilter\Truncate;
use Sigmie\Index\Analysis\TokenFilter\Unique;
use Sigmie\Index\Analysis\TokenFilter\Uppercase;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\TokenFilter;
use Sigmie\Shared\Collection;

trait Filters
{
    private Collection $filters;

    abstract public function analysis(): Analysis;

    public function tokenFilter(TokenFilter $tokenfilter): static
    {
        $this->addFilter($tokenfilter);

        return $this;
    }

    public function stemming(array $stemming, null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('stemming');

        $this->addFilter(new Stemmer($name, $stemming));

        return $this;
    }

    public function decimalDigit(null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('decimal_digit');

        $this->addFilter(new DecimalDigit($name));

        return $this;
    }

    public function asciiFolding(null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('ascii_folding');

        $this->addFilter(new AsciiFolding($name));

        return $this;
    }

    public function stopwords(array $stopwords, null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('stopwords');

        $this->addFilter(new Stopwords($name, $stopwords));

        return $this;
    }

    public function tokenLimit(int $maxTokenCount, null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('token_limit');

        $this->addFilter(new TokenLimit($name, $maxTokenCount));

        return $this;
    }

    public function trim(null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('trim');

        $this->addFilter(new Trim($name));

        return $this;
    }

    public function truncate(int $length = 10, null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('truncate');

        $this->addFilter(new Truncate($name, $length));

        return $this;
    }

    public function unique(bool $onlyOnSamePosition = false, null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('unique');

        $this->addFilter(new Unique($name, $onlyOnSamePosition));

        return $this;
    }

    public function uppercase(null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('uppercase');

        $this->addFilter(new Uppercase($name));

        return $this;
    }

    public function lowercase(null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('lowercase');

        $this->addFilter(new Lowercase($name));

        return $this;
    }

    public function keywords(array $keywords, null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('keywords');

        $this->addFilter(new Keywords($name, $keywords));

        return $this;
    }

    public function oneWaySynonyms(array $synonyms, null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('synonyms');

        $this->addFilter(new Synonyms($name, $synonyms, expand: false));

        return $this;
    }

    public function twoWaySynonyms(array $synonyms, null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('synonyms');

        $this->addFilter(new Synonyms($name, $synonyms, expand: true));

        return $this;
    }

    public function synonyms(array $synonyms, bool $expand = true, null|string $name = null): static
    {
        $name = $name ?? $this->createFilterName('synonyms');

        $this->addFilter(new Synonyms($name, $synonyms, $expand));

        return $this;
    }

    public function filters(): array
    {
        $this->initFilters();

        return $this->filters->toArray();
    }

    protected function addFilter(TokenFilter $tokenFilter): void
    {
        $this->initFilters();

        $this->ensureFilterNameIsAvailable($tokenFilter->name());

        $this->analysis()->addFilters([$tokenFilter->name() => $tokenFilter]);

        $this->filters->set($tokenFilter->name(), $tokenFilter);
    }

    private function initFilters(): void
    {
        $this->filters = $this->filters ?? new Collection();
    }

    private function ensureFilterNameIsAvailable(string $name): void
    {
        if ($this->analysis()->hasFilter($name)) {
            throw new Exception("Token filter `{$name}` already exists.");
        }
    }

    private function createFilterName(string $name): string
    {
        $suffixed = $name.'_'.random_letters();

        while ($this->analysis()->hasFilter($suffixed)) {
            $suffixed = $name.'_'.random_letters();
        }

        return $suffixed;
    }
}
