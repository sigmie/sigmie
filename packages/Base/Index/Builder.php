<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\CharFilter\Mapping;
use Sigmie\Base\Analysis\CharFilter\Pattern;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Support\Shared\Filters;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\Tokenizer as TokenizerInterface;
use Sigmie\Support\Exceptions\MissingMapping;
use Sigmie\Support\Alias\Actions as IndexActions;
use Sigmie\Base\Analysis\Analysis;
use Sigmie\Base\Contracts\Analysis as AnalysisInterface;
use Sigmie\Support\Shared\CharFilters;
use Sigmie\Support\Shared\Tokenizer;
use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Support\Index\AliasedIndex;
use Sigmie\Support\Index\TokenizerBuilder;

use function Sigmie\Helpers\index_name;

use Sigmie\Support\Shared\Mappings;
use Sigmie\Support\Shared\Replicas;
use Sigmie\Support\Shared\Shards;

class Builder
{
    use IndexActions, Actions, Filters, Mappings, CharFilters, Shards, Replicas, Tokenizer;

    protected string $alias;

    protected Language $language;

    private DefaultAnalyzer $defaultAnalyzer;

    private AnalysisInterface $analysis;

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


    public function getAnalyzer(): Analyzer
    {
        $analyzer = $this->defaultAnalyzer ?? new DefaultAnalyzer();

        if (!isset($this->defaultAnalyzer)) {
            $this->defaultAnalyzer = $analyzer;
        }

        return $analyzer;
    }

    public function create(): Index
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
            analysis: $this->analysis
        );

        $indexName = index_name($this->alias);

        $index = new AliasedIndex($indexName, $this->alias, [], $settings, $mappings);

        $index = $this->createIndex($index);

        $this->createAlias($index->name(), $this->alias);

        return $index;
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
