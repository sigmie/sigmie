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
use Sigmie\Base\Exceptions\MissingMapping;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Analysis\Analysis;
use function Sigmie\Helpers\index_name;

use Sigmie\Support\Shared\Mappings;

class Builder
{
    use IndexActions, AliasActions, DefaultFilters, Mappings;

    protected int $replicas = 2;

    protected int $shards = 1;

    protected string $alias;

    protected Language $language;

    protected Tokenizer $tokenizer;

    protected array $charFilter = [];

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

    public function tokenizeOn(Tokenizer $tokenizer)
    {
        $this->tokenizer = $tokenizer;

        return $this;
    }

    public function stripHTML()
    {
        $this->charFilter[] = new HTMLFilter;

        return $this;
    }

    public function patternReplace(
        string $pattern,
        string $replace,
        string|null $name = null
    ) {
        $name = $name ?: 'default' . '_pattern_replace_filter';

        $this->charFilter[] = new PatternFilter($name, $pattern, $replace);

        return $this;
    }

    public function mapChars(array $mappings, string|null $name = null)
    {
        $name = $name ?: 'default' . '_mappings_filter';

        $this->charFilter[] = new MappingFilter($name, $mappings);

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

    public function create()
    {
        $this->throwUnlessMappingsDefined();

        $defaultFilters = $this->defaultFilters();

        $defaultAnalyzer = new DefaultAnalyzer($this->tokenizer, $defaultFilters, $this->charFilter);

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
            throw new MissingMapping();
        }
    }

    protected function languageIsDefined(): bool
    {
        return isset($this->language);
    }
}
