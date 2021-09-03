<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\Analysis;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\Analysis as AnalysisInterface;
use Sigmie\Base\Contracts\HttpConnection;
use function Sigmie\Helpers\index_name;
use Sigmie\Support\Alias\Actions as IndexActions;
use Sigmie\Support\Exceptions\MissingMapping;
use Sigmie\Support\Index\AliasedIndex;
use Sigmie\Support\Index\TokenizerBuilder;
use Sigmie\Support\Shared\CharFilters;
use Sigmie\Support\Shared\Filters;

use Sigmie\Support\Shared\Mappings;

use Sigmie\Support\Shared\Replicas;
use Sigmie\Support\Shared\Shards;
use Sigmie\Support\Shared\Tokenizer;

class Builder
{
    use IndexActions, Actions, Filters, Mappings, CharFilters, Shards, Replicas, Tokenizer;

    protected string $alias;

    protected DefaultAnalyzer $defaultAnalyzer;

    protected AnalysisInterface $analysis;

    protected array $config = [];

    public function __construct(HttpConnection $connection)
    {
        $this->tokenizer = new WordBoundaries();

        $this->setHttpConnection($connection);

        $this->analysis = new Analysis([$this->getAnalyzer()]);
    }

    public function analysis(): AnalysisInterface
    {
        return $this->analysis;
    }

    public function tokenizeOn(): TokenizerBuilder
    {
        return new TokenizerBuilder($this);
    }

    public function getPrefix(): string
    {
        return $this->alias;
    }

    public function config(string $name, string|array|bool|int $value): static
    {
        $this->config[$name] = $value;

        return $this;
    }

    public function alias(string $alias): static
    {
        $this->alias = $alias;

        return $this;
    }

    public function withoutMappings(): static
    {
        $this->dynamicMappings = true;

        return $this;
    }


    public function getAnalyzer(): DefaultAnalyzer
    {
        $analyzer = $this->defaultAnalyzer ?? new DefaultAnalyzer();

        if (!isset($this->defaultAnalyzer)) {
            $this->defaultAnalyzer = $analyzer;
        }

        return $analyzer;
    }

    public function create(): Index
    {
        $index = $this->make();

        $index = $this->createIndex($index);

        $this->createAlias($index->name(), $this->alias);

        return $index;
    }

    public function make(): AliasedIndex
    {
        $this->throwUnlessMappingsDefined();

        $defaultAnalyzer = $this->getAnalyzer();
        $defaultAnalyzer->addCharFilters($this->charFilters());
        $defaultAnalyzer->addFilters($this->filters());
        $defaultAnalyzer->updateTokenizer($this->tokenizer);

        $mappings = $this->createMappings($defaultAnalyzer);

        $analyzers = $mappings->analyzers();

        $this->analysis()->addAnalyzers($analyzers);

        if ($this->languageIsDefined()) {
            $this->analysis()->addLanguageFilters($this->language);
        }

        $settings = new Settings(
            primaryShards: $this->shards,
            replicaShards: $this->replicas,
            analysis: $this->analysis,
            configs: $this->config
        );


        $indexName = index_name($this->alias);

        return new AliasedIndex($indexName, $this->alias, [], $settings, $mappings);
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
