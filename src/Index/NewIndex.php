<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Carbon\Carbon;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Languages\English\Filter\Lowercase;
use Sigmie\Languages\English\Filter\Stemmer;
use Sigmie\Languages\English\Filter\Stopwords;
use Sigmie\Index\Actions as IndexActions;
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
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Properties as MappingsProperties;
use Sigmie\Semantic\Contracts\AIProvider;
use Sigmie\Semantic\Providers\SigmieAI as DefaultEmbeddingsProvider;
use Sigmie\Shared\EmbeddingsProvider;

class NewIndex
{
    use Autocomplete;
    use CharFilters;
    use Filters;
    use SearchSynonyms;
    use IndexActions;
    use Mappings;
    use Replicas;
    use Shards;
    use Tokenizer;
    use EmbeddingsProvider;

    protected string $language = 'no_lang';

    protected string $alias;

    protected DefaultAnalyzer $defaultAnalyzer;

    protected AnalysisInterface $analysis;

    protected array $config = [];

    protected Properties $properties;

    protected function autocompleteTokenFilters(): array
    {
        return [
            new Stemmer('autocomplete_english_stemmer'),
            new Stopwords('autocomplete_english_stopwords'),
            new Lowercase('autocomplete_english_lowercase'),
        ];
    }

    public function __construct(ElasticsearchConnection $connection)
    {
        $this->setElasticsearchConnection($connection);

        $this->tokenizer = new WordBoundaries();

        $this->analysis = new Analysis();

        $this->properties = new MappingsProperties;

        $this->aiProvider = new DefaultEmbeddingsProvider();
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

        return $builder;
    }

    public function defaultAnalyzer(): DefaultAnalyzer
    {
        $this->defaultAnalyzer ?? $this->defaultAnalyzer = new DefaultAnalyzer();

        return $this->defaultAnalyzer;
    }

    public function create(): AliasedIndex
    {
        $index = $this->make();

        $this->createIndex($index->name, $index->settings, $index->mappings);

        $this->createAlias($index->name, $this->alias);

        $index = new AliasedIndex($index->name, $this->alias);
        $index->setElasticsearchConnection($this->elasticsearchConnection);

        return $index;
    }

    public function save(string $name, array $patterns): IndexTemplate
    {
        $index = $this->make();

        $template = $this->saveIndexTemplate(
            $name,
            $patterns,
            $index->settings,
            $index->mappings
        );

        return $template;
    }

    public function make(): Index
    {
        $defaultAnalyzer = $this->defaultAnalyzer();
        $defaultAnalyzer->addCharFilters($this->charFilters());
        $defaultAnalyzer->addFilters($this->filters());
        $defaultAnalyzer->setTokenizer($this->tokenizer);

        /** @var IndexMappings $mappings */
        $mappings = $this->createMappings($defaultAnalyzer);
        $mappings->aiProvider($this->aiProvider);

        $analyzers = $mappings->analyzers();

        $this->analysis()->addAnalyzers($analyzers);

        if ($this->searchSynonyms) {
            $this->analysis()->addAnalyzer($this->makeSearchSynonymsAnalyzer());
        }

        $settings = new Settings(
            primaryShards: $this->shards,
            replicaShards: $this->replicas,
            analysis: $this->analysis,
            configs: $this->config
        );

        if ($this->autocomplete && ($mappings->properties()->autocompleteField ?? false)) {
            $pipeline = $this->createAutocompletePipeline($mappings);

            $settings->defaultPipeline($pipeline->name);
        }

        $name = $this->createIndexName();

        $index = new Index($name, $settings, $mappings);

        return $index;
    }

    protected function createIndexName() {

        $timestamp = Carbon::now()->format('YmdHisu');

        return "{$this->alias}_{$timestamp}";
    }
}
