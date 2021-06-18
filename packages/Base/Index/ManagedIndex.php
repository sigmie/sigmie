<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Carbon\Carbon;
use Sigmie\Base\Analysis\DefaultFilters;
use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Contracts\ManagedIndex as ManagedIndexInterface;

class ManagedIndex implements ManagedIndexInterface
{
    use DefaultFilters, Actions, AliasActions;

    protected array $filters = [];

    public function __construct(protected Index $index)
    {
        $this->setHttpConnection($index->getHttpConnection());

        $filters = $this->index->getSettings()->analysis->defaultAnalyzer()->filters();

        foreach ($filters as $filter) {
            $this->filters[$filter::class] = $filter;
        }

        $this->stopwords = $this->filters[Stopwords::class];
        $this->oneWaySynonyms = $this->filters[OneWaySynonyms::class];
        $this->twoWaySynonyms = $this->filters[TwoWaySynonyms::class];
        $this->stemming = $this->filters[Stemmer::class];
    }

    public function getPrefix(): string
    {
        return $this->index->name();
    }

    public function update()
    {
        $filters = $this->defaultFilters();

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
