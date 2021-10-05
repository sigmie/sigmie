<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\Analysis;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\Analysis as AnalysisInterface;
use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Language;

use function Sigmie\Helpers\index_name;
use Sigmie\Support\Exceptions\MissingMapping;
use Sigmie\Base\Index\AliasedIndex;
use Sigmie\Support\Index\TokenizerBuilder;
use Sigmie\Support\Shared\CharFilters;
use Sigmie\Support\Shared\Filters;

use Sigmie\Support\Shared\Mappings;

use Sigmie\Support\Shared\Replicas;
use Sigmie\Support\Shared\Shards;
use Sigmie\Support\Shared\Tokenizer;

class Builder
{
    use IndexActions, Filters, Mappings, CharFilters, Shards, Replicas, Tokenizer;

    protected string $alias;

    protected DefaultAnalyzer $defaultAnalyzer;

    protected array $config = [];

    public function __construct(HttpConnection $connection)
    {
        $this->setHttpConnection($connection);

        $this->tokenizer = new WordBoundaries();

        $this->analysis = new Analysis();
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

    public function language(Language $language)
    {
        $builder = $language->builder($this->getHttpConnection());

        $builder->alias($this->alias);

        return  $builder;
    }


    public function defaultAnalyzer(): DefaultAnalyzer
    {
        $this->defaultAnalyzer ?? $this->defaultAnalyzer = new DefaultAnalyzer();

        return $this->defaultAnalyzer;
    }

    public function create(): AbstractIndex
    {
        $index = $this->make();

        $indexName = index_name($this->alias);

        $this->createIndex($indexName, $index);

        $this->createAlias($indexName, $this->alias);

        $index = new AliasedIndex($indexName, $this->alias);
        $index->setHttpConnection($this->httpConnection);

        return $index;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function make(): IndexBlueprint
    {
        $this->throwUnlessMappingsDefined();

        $defaultAnalyzer = $this->defaultAnalyzer();
        $defaultAnalyzer->addCharFilters($this->charFilters());
        $defaultAnalyzer->addFilters($this->filters());
        $defaultAnalyzer->updateTokenizer($this->tokenizer);

        $mappings = $this->createMappings($defaultAnalyzer);

        $analyzers = $mappings->analyzers();

        $this->analysis()->addAnalyzers($analyzers);

        $settings = new Settings(
            primaryShards: $this->shards,
            replicaShards: $this->replicas,
            analysis: $this->analysis,
            configs: $this->config
        );


        return new IndexBlueprint($settings, $mappings);
    }

    protected function throwUnlessMappingsDefined(): void
    {
        if ($this->dynamicMappings === false && isset($this->blueprintCallback) === false) {
            throw MissingMapping::forAlias($this->alias);
        }
    }
}
