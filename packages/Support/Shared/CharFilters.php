<?php

declare(strict_types=1);

namespace Sigmie\Support\Shared;

use Exception;
use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\CharFilter\Mapping;
use Sigmie\Base\Analysis\CharFilter\Pattern;
use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\Synonyms;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Contracts\Analysis;
use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Support\Collection as SupportCollection;
use Sigmie\Support\Contracts\Collection;

use function Sigmie\Helpers\random_letters;

trait CharFilters
{
    private Collection $charFilters;

    public function charFilter(CharFilter $charFilter)
    {
        $this->addCharFilter($charFilter);

        return $this;
    }

    private function ensureCharFilterNameIsAvailable(string $name): void
    {
        if ($this->analysis()->hasCharFilter($name)) {
            throw new Exception('Char filter name already exists.');
        }
    }

    abstract function analysis(): Analysis;

    private function addCharFilter(CharFilter $charFilter): void
    {
        $this->initCharFilters();

        $this->ensureCharFilterNameIsAvailable($charFilter->name());

        $this->analysis()->updateCharFilters([$charFilter->name() => $charFilter]);

        $this->charFilters->set($charFilter->name(), $charFilter);
    }

    private function initCharFilters(): void
    {
        $this->charFilters  = $this->charFilters ?? new SupportCollection();
    }

    public function charFilters(): array
    {
        $this->initCharFilters();

        return $this->charFilters->toArray();
    }

    public function stripHTML(): static
    {
        $this->addCharFilter(new HTMLStrip);

        return $this;
    }

    private function createCharFilterName(string $name): string
    {
        $suffixed = $name . '_' . random_letters();

        while ($this->analysis()->hasCharFilter($suffixed)) {
            $suffixed = $name . '_' . random_letters();
        }

        return $suffixed;
    }

    public function patternReplace(
        string $pattern,
        string $replace,
        string|null $name = null
    ): static {
        $name = $name ?? $this->createCharFilterName('pattern_replace_filter');

        $this->addCharFilter(new Pattern($name, $pattern, $replace));

        return $this;
    }

    public function mapChars(array $mappings, string|null $name = null): static
    {
        $name = $name ?? $this->createCharFilterName('mapping_filter');

        $this->addCharFilter(new Mapping($name, $mappings));

        return $this;
    }
}
