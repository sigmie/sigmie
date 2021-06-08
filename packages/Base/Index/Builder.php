<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Carbon\Carbon;
use Closure;
use Sigmie\Base\Index\AliasedIndex;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Analysis\DefaultFilters;
use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\CharFilter as ContractsCharFilter;
use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Base\Exceptions\MissingMapping;
use Sigmie\Base\Index\Actions as IndexActions;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Builder
{
    use IndexActions, AliasActions, DefaultFilters;

    protected int $replicas = 2;

    protected int $shards = 1;

    protected string $alias;

    protected Language $language;

    protected bool $dynamicMappings = false;

    protected Tokenizer $tokenizer;


    protected array $charFilter = [];

    protected Closure $blueprintCallback;

    public function __construct(HttpConnection $connection, EventDispatcherInterface $events)
    {
        $this->events = $events;
        $this->tokenizer = new WordBoundaries();

        $this->setHttpConnection($connection);
    }

    public function getPrefix(): string
    {
        return $this->alias;
    }

    public function alias(string $alias)
    {
        $this->alias = $alias;

        return $this;
    }

    public function withoutMappings()
    {
        $this->dynamicMappings = true;

        return $this;
    }

    public function tokenizeOn(Tokenizer $tokenizer)
    {
        $this->tokenizer = $tokenizer;

        return $this;
    }

    public function mappings(callable $callable)
    {
        $this->blueprintCallback = $callable;

        return $this;
    }

    public function normalizer(ContractsCharFilter $charFilter)
    {
        $this->charFilter[] = $charFilter;

        return $this;
    }


    public function shards(int $shards)
    {
        $this->shards = $shards;

        return $this;
    }

    public function replicas(int $replicas)
    {
        $this->replicas = $replicas;

        return $this;
    }

    protected function throwUnlessMappingsDefined(): void
    {
        if ($this->dynamicMappings === false && isset($this->blueprintCallback) === false) {
            throw new MissingMapping();
        }
    }

    public function create()
    {
        $timestamp = Carbon::now()->format('YmdHisu');

        $this->throwUnlessMappingsDefined();

        $defaultFilters = $this->defaultFilters();

        $defaultAnalyzer = new DefaultAnalyzer(
            $this->alias,
            $this->tokenizer,
            $this->stopwords ?: null,
            $this->twoWaySynonyms ?: null,
            $this->oneWaySynonyms ?: null,
            $this->stemming ?: null
        );

        // $defaultAnalyzer = new Analyzer(
        //     prefix: $this->alias,
        //     tokenizer: $this->tokenizer,
        //     filters: $defaultFilters,
        //     charFilters: $this->charFilter
        // );

        $mappings = $this->createMappings($defaultAnalyzer);
        $analyzers = $mappings->analyzers();
        // $analyzers->add($defaultAnalyzer);

        $analysis = new Analysis(
            defaultAnalyzer: $defaultAnalyzer,
            analyzers: $analyzers->toArray(),
        );


        if ($this->languageIsDefined()) {
            $analysis->addLanguageFilters($this->language);
        }

        $settings = new Settings(
            primaryShards: $this->shards,
            replicaShards: $this->replicas,
            analysis: $analysis
        );

        $indexName = "{$this->alias}_{$timestamp}";
        $index = new AliasedIndex($indexName, $this->alias, [], $settings, $mappings);

        $index = $this->createIndex($index);

        $this->createAlias($index->getName(), $this->alias);
    }

    protected function languageIsDefined(): bool
    {
        return isset($this->language);
    }

    protected function createMappings(Analyzer $defaultAnalyzer): Mappings
    {
        $mappings = new DynamicMappings($defaultAnalyzer);

        if ($this->dynamicMappings === false) {
            $blueprint = ($this->blueprintCallback)(new Blueprint);

            $properties = $blueprint($defaultAnalyzer);

            $mappings = new Mappings($properties, $defaultAnalyzer);
        }

        return $mappings;
    }
}
