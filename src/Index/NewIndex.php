<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Carbon\Carbon;
use RuntimeException;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\Contracts\CollectionHook;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\Alias\AliasAlreadyExists;
use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\Language;
use Sigmie\Index\Mappings as IndexMappings;
use Sigmie\Index\Shared\CharFilters;
use Sigmie\Index\Shared\Filters;
use Sigmie\Index\Shared\Mappings;
use Sigmie\Index\Shared\Replicas;
use Sigmie\Index\Shared\SearchSynonyms;
use Sigmie\Index\Shared\Shards;
use Sigmie\Index\Shared\Tokenizer;
use Sigmie\Languages\English\Filter\Lowercase;
use Sigmie\Languages\English\Filter\Stemmer;
use Sigmie\Languages\English\Filter\Stopwords;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Properties as MappingsProperties;
use Sigmie\Shared\UsesApis;
use Throwable;

class NewIndex
{
    use CharFilters;
    use Filters;
    use IndexActions;
    use Mappings;
    use Replicas;
    use SearchSynonyms;
    use Shards;
    use Tokenizer;
    use UsesApis;

    protected string $language = 'no_lang';

    protected string $alias;

    protected DefaultAnalyzer $defaultAnalyzer;

    protected AnalysisInterface $analysis;

    protected array $config = [];

    protected Properties $properties;

    /** @var array<int, CollectionHook> */
    protected array $collectionHooks = [];

    protected function autocompleteTokenFilters(): array
    {
        return [
            new Stemmer('autocomplete_english_stemmer'),
            new Stopwords('autocomplete_english_stopwords'),
            new Lowercase('autocomplete_english_lowercase'),
        ];
    }

    public function __construct(
        ElasticsearchConnection $connection,
    ) {
        $this->setElasticsearchConnection($connection);

        $this->tokenizer = new WordBoundaries;

        $this->analysis = new Analysis;

        $this->properties = new MappingsProperties;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function analysis(): AnalysisInterface
    {
        return $this->analysis;
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

    public function language(Language $language)
    {
        $builder = $language->builder($this->getElasticsearchConnection());

        $builder->alias($this->alias);
        $builder->shards($this->shards);
        $builder->replicas($this->replicas);
        $builder->apis($this->apis);
        $builder->collectionHooks($this->collectionHooks);

        return $builder;
    }

    /**
     * @param  array<int, CollectionHook>  $hooks
     */
    public function collectionHooks(array $hooks): static
    {
        $this->collectionHooks = $hooks;

        return $this;
    }

    /**
     * Create a physical index for this alias without attaching the alias,
     * tuned for bulk writes (no refresh, no replicas until finish()).
     * Fill it from one or many processes, then finish() or abort() it.
     *
     * The rebuild's collection carries no properties, like Sigmie::collect().
     * Call ->properties($props) on it to validate documents and populate
     * semantic fields.
     */
    public function startRebuild(): IndexRebuild
    {
        $replicas = $this->replicas;

        $this->meta([IndexRebuild::META_KEY => $this->alias, IndexRebuild::META_REPLICAS => $replicas]);
        $this->config('refresh_interval', '-1');
        $this->replicas(0);

        $index = $this->make();

        $this->createIndex($index->name, $index->settings, $index->mappings);

        $rebuild = new IndexRebuild($this->elasticsearchConnection, $this->alias, $index->name, $replicas, $this->collectionHooks);

        return $rebuild->apis($this->apis);
    }

    /**
     * Rebuild in one process: start, let $fill write every document, finish.
     *
     *   alias ──► movies_old            (searchable throughout)
     *             movies_new  ◄── $fill
     *   alias ──► movies_new            (one _aliases call)
     *             movies_old  deleted
     *
     * The live index is untouched when $fill throws or writes fewer than
     * $minDocuments documents.
     */
    public function rebuild(callable $fill, int $minDocuments = 1): AliasedIndex
    {
        $rebuild = $this->startRebuild();

        try {
            $fill($rebuild->collect());
        } catch (Throwable $throwable) {
            $rebuild->abort();

            throw $throwable;
        }

        return $rebuild->finish($minDocuments);
    }

    public function defaultAnalyzer(): DefaultAnalyzer
    {
        $this->defaultAnalyzer ?? $this->defaultAnalyzer = new DefaultAnalyzer;

        return $this->defaultAnalyzer;
    }

    public function create(): AliasedIndex
    {
        if ($this->aliasExists($this->alias)) {
            throw AliasAlreadyExists::forAlias($this->alias);
        }

        $index = $this->make();

        $this->createIndex($index->name, $index->settings, $index->mappings);

        $this->createAlias($index->name, $this->alias);

        $index = new AliasedIndex($index->name, $this->alias);
        $index->setElasticsearchConnection($this->elasticsearchConnection);

        return $index;
    }

    public function createIfNotExists(): AliasedIndex
    {
        if ($this->aliasExists($this->alias)) {
            $existingIndex = $this->getIndex($this->alias);

            if ($existingIndex instanceof AliasedIndex) {
                return $existingIndex;
            }

            throw new RuntimeException(sprintf("Index '%s' exists but is not an aliased index", $this->alias));
        }

        return $this->create();
    }

    public function save(string $name, array $patterns): IndexTemplate
    {
        $index = $this->make();

        return $this->saveIndexTemplate(
            $name,
            $patterns,
            $index->settings,
            $index->mappings
        );
    }

    public function make(): Index
    {
        $defaultAnalyzer = $this->defaultAnalyzer();
        $defaultAnalyzer->addCharFilters($this->charFilters());
        $defaultAnalyzer->addFilters($this->filters());
        $defaultAnalyzer->setTokenizer($this->tokenizer);

        /** @var IndexMappings $mappings */
        $mappings = $this->createMappings($defaultAnalyzer);

        $analyzers = $mappings->analyzers();

        $this->analysis()->addAnalyzers($analyzers);

        if ($this->searchSynonyms) {
            $this->analysis()->addAnalyzer($this->makeSearchSynonymsAnalyzer());
        }

        // Apply engine-specific index settings for semantic fields
        $driver = $this->elasticsearchConnection->driver();
        $engineSettings = $driver->indexSettings();
        $this->config = [...$this->config, ...$engineSettings];

        $useServerless = $this->serverless || $this->elasticsearchConnection->isServerless();

        $settings = new Settings(
            primaryShards: $useServerless ? null : $this->shards,
            replicaShards: $useServerless ? null : $this->replicas,
            analysis: $this->analysis,
            configs: $this->config
        );

        $name = $this->createIndexName();

        return new Index($name, $settings, $mappings);
    }

    protected function createIndexName(): string
    {
        $timestamp = Carbon::now()->format('YmdHisu');

        return sprintf('%s_%s', $this->alias, $timestamp);
    }
}
