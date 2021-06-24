<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Carbon\Carbon;
use Sigmie\Support\Shared\Filters;
use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Contracts\ManagedIndex as ManagedIndexInterface;

class ManagedIndex implements ManagedIndexInterface
{
    use Filters, Actions, Actions;

    protected array $charFilters = [];

    public function __construct(protected Index $index)
    {
        $this->setHttpConnection($index->getHttpConnection());

        $filters = $this->index->getSettings()->analysis->defaultAnalyzer()->filters();

        foreach ($filters as $filter) {
            $this->charFilters[$filter::class] = $filter;
        }

        $this->stopwords = $this->charFilters[Stopwords::class];
        $this->oneWaySynonyms = $this->charFilters[OneWaySynonyms::class];
        $this->twoWaySynonyms = $this->charFilters[TwoWaySynonyms::class];
        $this->stemming = $this->charFilters[Stemmer::class];
    }

    public function getPrefix(): string
    {
        return $this->index->name();
    }

    public function update()
    {
        $filters = $this->filters();

        $timestamp = Carbon::now()->format('YmdHisu');

        $indexName = "{$this->getPrefix()}_{$timestamp}";

        $settings = $this->index->getSettings();
        $defaultAnalyzer = $settings->analysis->defaultAnalyzer();
        $defaultAnalyzer->setFilters($filters);

        $settings->analysis->setDefaultAnalyzer($defaultAnalyzer);
        $mappings = $this->index->getMappings();

        $this->createIndex(new Index($indexName, $settings, $mappings));

        foreach ($this->index->getAliases() as $alias) {
            $this->switchAlias($alias, $this->index->name(), $indexName);
        }

        
    }
}
