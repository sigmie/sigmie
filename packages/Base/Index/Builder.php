<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\CharFilter\HTMLFilter;
use Sigmie\Base\Analysis\CharFilter\MappingFilter;
use Sigmie\Base\Analysis\CharFilter\PatternFilter;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Analysis\DefaultFilters;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\CharFilter as ContractsCharFilter;
use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Exceptions\MissingMapping;
use Sigmie\Support\Alias\Actions as IndexActions;
use Sigmie\Base\Analysis\Analysis;
use Sigmie\Base\Analysis\DefaultCharFilters;
use Sigmie\Base\Analysis\TokenizeOn;
use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Support\Index\AliasedIndex;

use function Sigmie\Helpers\index_name;

use Sigmie\Support\Shared\Mappings;

class Builder
{
    use IndexActions, Actions, DefaultFilters, Mappings, TokenizeOn, DefaultCharFilters;

    protected int $replicas = 2;

    protected int $shards = 1;

    protected string $alias;

    protected Language $language;

    protected Tokenizer $tokenizer;

    private DefaultAnalyzer $defaultAnalyzer;

    public function __construct(HttpConnection $connection)
    {
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

    public function tokenizer(Tokenizer $tokenizer)
    {
        $this->getAnalyzer()->updateTokenizer($tokenizer);

        return $this;
    }

    public function charFilter(ContractsCharFilter $charFilter)
    {
        $this->addCharFilter($charFilter);

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

    public function getAnalyzer(): Analyzer
    {
        $analyzer = $this->defaultAnalyzer ?? new DefaultAnalyzer();

        if (!isset($this->defaultAnalyzer)) {
            $this->defaultAnalyzer = $analyzer;
        }

        return $analyzer;
    }

    public function create()
    {
        $this->throwUnlessMappingsDefined();

        $defaultFilters = $this->defaultFilters();
        $defaultCharFilters = $this->defaultCharFilters();

        $defaultAnalyzer = $this->getAnalyzer();
        $defaultAnalyzer->addCharFilters($defaultCharFilters);
        $defaultAnalyzer->addFilters($defaultFilters);

        $mappings = $this->createMappings($defaultAnalyzer);

        $analyzers = $mappings->analyzers();

        $analysis = new Analysis(
            defaultAnalyzer: $defaultAnalyzer,
            analyzers: $analyzers,
        );

        if ($this->languageIsDefined()) {
            $analysis->addLanguageFilters($this->language);
        }

        $settings = new Settings(
            primaryShards: $this->shards,
            replicaShards: $this->replicas,
            analysis: $analysis
        );

        $indexName = index_name($this->alias);

        $index = new AliasedIndex($indexName, $this->alias, [], $settings, $mappings);

        $index = $this->createIndex($index);

        $this->createAlias($index->name(), $this->alias);
    }

    protected function throwUnlessMappingsDefined(): void
    {
        if ($this->dynamicMappings === false && isset($this->blueprintCallback) === false) {
            throw MissingMapping::forAlias($this->alias);
        }
    }

    protected function languageIsDefined(): bool
    {
        return isset($this->language);
    }
}
