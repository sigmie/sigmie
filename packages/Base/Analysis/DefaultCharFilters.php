<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\CharFilter\HTMLFilter;
use Sigmie\Base\Analysis\CharFilter\MappingFilter;
use Sigmie\Base\Analysis\CharFilter\PatternFilter;
use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\Synonyms;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Support\Collection as SupportCollection;
use Sigmie\Support\Contracts\Collection;

use function Sigmie\Helpers\random_letters;

trait DefaultCharFilters
{
    private Collection $charFilters;

    private int $priority = 1;

    private function addCharFilter(CharFilter $charFilter)
    {
        $this->initCharFilters();

        $charFilter->setPriority($this->priority);

        $this->charFilters->set($charFilter->name(), $charFilter);

        $this->priority++;
    }

    private function initCharFilters(): void
    {
        $this->charFilters  = $this->charFilters ?? new SupportCollection();
    }

    public function defaultCharFilters(): array
    {
        $this->initCharFilters();

        return $this->charFilters->toArray();
    }

    public function stripHTML()
    {
        $this->addCharFilter(new HTMLFilter);

        return $this;
    }

    public function patternReplace(
        string $pattern,
        string $replace,
        string|null $name = null
    ) {
        $name = $name ?? 'pattern_replace_filter_' . random_letters();

        $this->addCharFilter(new PatternFilter($name, $pattern, $replace));

        return $this;
    }

    public function mapChars(array $mappings, string|null $name = null)
    {
        $name = $name ?? 'mapping_filter_' . random_letters();

        $this->addCharFilter(new MappingFilter($name, $mappings));

        return $this;
    }
}
