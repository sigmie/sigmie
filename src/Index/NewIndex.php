<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Document\Contracts\CollectionHook;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\Alias\AliasAlreadyExists;
use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Index\Contracts\CustomAnalyzer;
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
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Shared\UsesApis;
use Throwable;

use function Sigmie\Functions\merge_named;

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

    protected AnalysisInterface $analysis;

    protected array $config = [];

    protected Properties $properties;

    protected Analysis $fieldAnalysis;

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

        $this->analysis = new Analysis;
        $this->analysis->defaultAnalyzer();

        $this->fieldAnalysis = new Analysis;

        $this->properties = new Properties;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function analysis(): AnalysisInterface
    {
        return $this->analysis;
    }

    public function properties(NewProperties $props): static
    {
        $analysis = new Analysis;
        $this->properties = $props->get(analysis: $analysis);
        $this->fieldAnalysis = $analysis;

        return $this;
    }

    public function config(string $name, string|array|bool|int|float|null $value): static
    {
        if ($name === 'index' && is_array($value)) {
            foreach ($value as $setting => $configured) {
                $this->config('index.'.$setting, $configured);
            }

            return $this;
        }

        $key = str_starts_with($name, 'index.') ? substr($name, 6) : $name;
        if (in_array($key, ['blocks', 'write'], true) && is_array($value)) {
            foreach ($value as $setting => $configured) {
                $this->config($key.'.'.$setting, $configured);
            }

            return $this;
        }

        if ($key === 'number_of_shards' || $key === 'number_of_replicas') {
            if ($value === null) {
                return $this;
            }

            if (! is_int($value) && (! is_string($value) || ! preg_match('/^[+-]?\d+$/D', $value))) {
                throw new InvalidArgumentException(sprintf("The index setting '%s' requires an integer.", $name));
            }

            return $key === 'number_of_shards'
                ? $this->shards((int) $value)
                : $this->replicas((int) $value);
        }

        unset($this->config[$key], $this->config['index.'.$key]);
        $this->config[in_array($key, ['refresh_interval', 'analysis', 'blocks.read', 'blocks.write', 'write.wait_for_active_shards'], true) ? $key : $name] = $value;

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

        if (isset($this->alias)) {
            $builder->alias($this->alias);
        }

        if ($builder instanceof self) {
            $builder->config = array_replace($builder->config, $this->config);
            $builder->meta($this->customMeta);
            if ($this->properties->hasFields()) {
                $builder->properties = $this->properties->copy();
                $builder->fieldAnalysis = clone $this->fieldAnalysis;
            }

            $builder->searchSynonyms = $this->searchSynonyms ?? $builder->searchSynonyms;

            $analysis = clone $this->analysis;
            $defaultAnalyzer = $analysis->defaultAnalyzer();
            $builder->analysis->registerFieldComponents($analysis);
            $languageAnalyzer = $builder->defaultAnalyzer();
            if ($defaultAnalyzer instanceof CustomAnalyzer && $languageAnalyzer instanceof CustomAnalyzer) {
                $defaultAnalyzer->addFilters($languageAnalyzer->filters());
                $defaultAnalyzer->addCharFilters($languageAnalyzer->charFilters());
                $tokenizer = $languageAnalyzer->tokenizer();
                if ($tokenizer instanceof Contracts\Tokenizer && $tokenizer != new WordBoundaries) {
                    $defaultAnalyzer->setTokenizer($tokenizer);
                }
            }

            $analysis->addAnalyzer($defaultAnalyzer);
            $builder->analysis = $analysis;
        }

        $builder->shards($this->shards);
        $builder->replicas($this->replicas);
        $builder->apis($this->apis);
        $builder->collectionHooks($this->collectionHooks);
        $builder->serverless($this->serverless);

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
     * tuned for bulk writes (refresh disabled, replicas kept only when required).
     * Fill it from one or many processes, then finish() or abort() it.
     *
     * The rebuild's collection carries no properties, like Sigmie::collect().
     * Call ->properties($props) on it to validate documents and populate
     * semantic fields.
     */
    public function startRebuild(): IndexRebuild
    {
        $replicas = $this->replicas;
        $requestedReplicas = $this->usesServerlessSettings() ? null : $replicas;
        $refresh = (string) ($this->config['refresh_interval'] ?? ($this->elasticsearchConnection->isServerless() ? '5s' : '1s'));
        $sources = $this->aliasSources($this->alias);
        $targets = array_map('strval', array_keys($sources));
        $routing = $sources[array_key_first($sources)]['aliases'][$this->alias]['index_routing'] ?? null;
        $pendingSettings = ['is_hidden' => true];
        if ($routing !== null) {
            $pendingSettings['index_routing'] = $routing;
        }

        $config = $this->config;
        $meta = $this->customMeta;

        try {
            $deferredBlocks = $this->deferAccessBlocks();
            $this->meta([
                IndexRebuild::META_KEY => $this->alias,
                IndexRebuild::META_REPLICAS => $requestedReplicas,
                IndexRebuild::META_REFRESH => $refresh,
                IndexRebuild::META_TARGETS => $targets,
                IndexRebuild::META_BLOCKS => $deferredBlocks,
            ]);
            $this->config('refresh_interval', '-1');
            $this->useBulkReplicas();

            $index = $this->make();
            $this->createIndex($index->name, $index->settings, $index->mappings, [
                IndexRebuild::pendingAlias($index->name) => $pendingSettings,
            ]);
        } finally {
            $this->replicas($replicas);
            $this->config = $config;
            $this->customMeta = $meta;
        }

        $rebuild = new IndexRebuild(
            $this->elasticsearchConnection, $this->alias, $index->name, $requestedReplicas,
            $this->collectionHooks, $targets, $refresh, deferredBlocks: $deferredBlocks, fillRouting: $routing,
        );

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
            try {
                $rebuild->abort();
            } catch (Throwable $abortError) {
                throw new RuntimeException(sprintf("Rebuild fill failed and abort of '%s' also failed. ", $rebuild->name).$abortError->getMessage(), $throwable->getCode(), previous: $throwable);
            }

            throw $throwable;
        }

        return $rebuild->finish($minDocuments);
    }

    public function defaultAnalyzer(): Analyzer
    {
        return $this->analysis()->defaultAnalyzer();
    }

    protected function pipeline(): CustomAnalyzer
    {
        $analyzer = $this->defaultAnalyzer();
        if (! $analyzer instanceof CustomAnalyzer) {
            throw new InvalidArgumentException('The default analyzer must be custom to configure its pipeline.');
        }

        return $analyzer;
    }

    public function create(): AliasedIndex
    {
        if ($this->indexExists($this->alias)) {
            throw AliasAlreadyExists::forAlias($this->alias);
        }

        $index = $this->make();

        $this->createIndex($index->name, $index->settings, $index->mappings, [
            $this->alias => ['is_write_index' => true],
        ]);

        $index = new AliasedIndex($index->name, $this->alias, $index->settings, $index->mappings);
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

        try {
            return $this->create();
        } catch (AliasAlreadyExists|ElasticsearchException $exception) {
            if ($exception instanceof ElasticsearchException && ! in_array(
                $exception->json('type'),
                ['illegal_state_exception', 'resource_already_exists_exception'],
                true,
            )) {
                throw $exception;
            }

            $existingIndex = $this->getIndex($this->alias);
            if ($existingIndex instanceof AliasedIndex) {
                return $existingIndex;
            }

            throw $exception;
        }
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
        $analysis = clone $this->analysis();
        $defaultAnalyzer = $analysis->defaultAnalyzer();

        /** @var IndexMappings $mappings */
        $mappings = $this->createMappings($defaultAnalyzer);

        $analyzers = $mappings->analyzers();

        $this->fieldAnalysis->registerFieldComponents($analysis);
        $analysis->addAnalyzers($analyzers);

        // Apply engine-specific index settings for semantic fields
        $driver = $this->elasticsearchConnection->driver();
        $engineSettings = $driver->indexSettings();
        foreach (array_keys($this->config) as $setting) {
            unset($engineSettings['index.'.$setting]);
        }

        $useServerless = $this->usesServerlessSettings();

        $settings = new Settings(
            primaryShards: $useServerless ? null : $this->shards,
            replicaShards: $useServerless ? null : $this->replicas,
            analysis: $analysis,
            configs: merge_named($engineSettings, $this->config)
        );

        if ($this->searchSynonyms !== null) {
            $effectiveDefault = $settings->analysis()->defaultAnalyzer();
            if (! $effectiveDefault instanceof CustomAnalyzer) {
                throw new InvalidArgumentException('Search synonyms require a custom default analyzer.');
            }

            $analysis->addAnalyzer($this->makeSearchSynonymsAnalyzer($analysis, $effectiveDefault));
        }

        $name = $this->createIndexName();

        return new Index($name, $settings, $mappings);
    }

    public function useBulkReplicas(): void
    {
        $required = $this->config['write.wait_for_active_shards'] ?? 1;
        if ($required !== 'all' && (int) $required <= 1) {
            $this->replicas(0);
        }
    }

    /** @return list<string> Settings to restore after copying and counting documents. */
    public function deferAccessBlocks(): array
    {
        $blocks = [];
        foreach (['blocks.read', 'blocks.write'] as $block) {
            if (in_array($this->config[$block] ?? false, [true, 'true'], true)) {
                $this->config($block, false);
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    private function usesServerlessSettings(): bool
    {
        return $this->serverless || $this->elasticsearchConnection->isServerless();
    }

    public static function physicalNamePrefix(string $alias): string
    {
        // The separator and timestamp use 21 of the 255 bytes in an index name.
        if (strlen($alias) > 234 || mb_strtolower($alias, 'UTF-8') !== $alias) {
            return 'sigmie-'.hash('sha256', $alias);
        }

        return $alias;
    }

    protected function createIndexName(): string
    {
        $timestamp = Carbon::now('UTC')->format('YmdHisu');

        return sprintf('%s_%s', self::physicalNamePrefix($this->alias), $timestamp);
    }
}
