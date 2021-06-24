<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\CharFilter\HTMLFilter;
use Sigmie\Base\Analysis\CharFilter\MappingFilter;
use Sigmie\Base\Analysis\CharFilter\PatternFilter;
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

use function Sigmie\Helpers\index_name;

use Sigmie\Support\Shared\Mappings;

class Builder
{
    use IndexActions, Actions, Filters, Mappings, Tokenizer, CharFilters;

    protected int $replicas = 2;

    protected int $shards = 1;

    protected string $alias;

    protected Language $language;

    protected TokenizerInterface $tokenizer;

    private DefaultAnalyzer $defaultAnalyzer;

    private AnalysisInterface $analysis;

    public function __construct(HttpConnection $connection)
    {
        $this->tokenizer = new WordBoundaries();

        $this->setHttpConnection($connection);

        $this->analysis = new Analysis(
            defaultAnalyzer: $this->getAnalyzer(),
        );
    }

    protected function analysis(): AnalysisInterface
    {
        return $this->analysis;
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

        $defaultAnalyzer = $this->getAnalyzer();
        $defaultAnalyzer->addCharFilters($this->charFilters());
        $defaultAnalyzer->addFilters($this->filters());

        $mappings = $this->createMappings($defaultAnalyzer);

        $analyzers = $mappings->analyzers();

        $this->analysis->addAnalyzers($analyzers);

        if ($this->languageIsDefined()) {
            $this->analysis->addLanguageFilters($this->language);
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
