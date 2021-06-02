<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Carbon\Carbon;
use Closure;
use Sigmie\Base\Analysis\Analyzer;
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
    use IndexActions, AliasActions;

    protected int $replicas = 2;

    protected int $shards = 1;

    protected string $prefix = 'sigmie';

    protected string $alias;

    protected Language $language;

    protected bool $dynamicMappings = false;

    protected Tokenizer $tokenizer;

    protected array $stopwords = [];

    protected array $twoWaySynonyms = [];

    protected array $oneWaySynonyms = [];

    protected array $stemming = [];

    protected array $charFilter = [];

    protected Closure $blueprintCallback;
    public function __construct(HttpConnection $connection, EventDispatcherInterface $events)
    {
        $this->events = $events;
        $this->tokenizer = new WordBoundaries();

        $this->setHttpConnection($connection);
    }

    public function alias(string $alias)
    {
        $this->alias = $alias;

        return $this;
    }

    public function language(Language $language)
    {
        $this->language = $language;

        return $this;
    }

    public function prefix(string $prefix)
    {
        $this->prefix = "$prefix";

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

    public function stopwords(array $stopwords)
    {
        $this->stopwords = $stopwords;

        return $this;
    }

    public function twoWaySynonyms(array $synonyms)
    {
        $this->twoWaySynonyms = $synonyms;

        return $this;
    }

    public function oneWaySynonyms(array $synonyms)
    {
        $this->oneWaySynonyms = $synonyms;

        return $this;
    }

    public function normalizer(ContractsCharFilter $charFilter)
    {
        $this->charFilter[] = $charFilter;

        return $this;
    }

    public function stemming(array $stemming)
    {
        $this->stemming = $stemming;

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

        $indexName = "{$this->prefix}_{$timestamp}";

        $this->throwUnlessMappingsDefined();

        $defaultFilters = [
            new Stopwords($this->prefix, $this->stopwords, 1),
            new TwoWaySynonyms($this->prefix, $this->twoWaySynonyms, 2),
            new OneWaySynonyms($this->prefix, $this->oneWaySynonyms, 3),
            new Stemmer($this->prefix, $this->stemming, 4)
        ];

        $defaultAnalyzer = new Analyzer(
            prefix: $this->prefix,
            tokenizer: $this->tokenizer,
            filters: $defaultFilters,
            charFilters: $this->charFilter
        );

        $mappings = $this->createMappings($defaultAnalyzer);
        $analyzers = $mappings->analyzers();
        $analyzers->add($defaultAnalyzer);

        $analysis = new Analysis(
            tokenizers: [$this->tokenizer],
            analyzers: $analyzers->toArray(),
            filters: $defaultFilters,
            charFilters: $this->charFilter,
            defaultAnalyzer: $defaultAnalyzer
        );

        if ($this->languageIsDefined()) {
            $analysis->addLanguageFilters($this->language);
        }

        $settings = new Settings(
            primaryShards: $this->shards,
            replicaShards: $this->replicas,
            analysis: $analysis
        );

        $this->createIndex(new Index($indexName, $settings, $mappings));

        $this->createAlias($indexName, $this->alias);
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
