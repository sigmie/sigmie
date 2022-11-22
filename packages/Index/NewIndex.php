<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use function Sigmie\Functions\index_name;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\Language;
use Sigmie\Mappings\Properties as MappingsProperties;
use Sigmie\Index\Shared\CharFilters;
use Sigmie\Index\Shared\Filters;
use Sigmie\Index\Shared\Mappings;
use Sigmie\Index\Shared\Replicas;
use Sigmie\Index\Shared\Shards;
use Sigmie\Index\Shared\Tokenizer;
use Sigmie\Shared\Properties;

class NewIndex
{
    use IndexActions;
    use Filters;
    use Mappings;
    use CharFilters;
    use Shards;
    use Replicas;
    use Tokenizer;
    use Properties;

    protected string $alias;

    protected DefaultAnalyzer $defaultAnalyzer;

    protected AnalysisInterface $analysis;

    protected array $config = [];

    public function __construct(ElasticsearchConnection $connection)
    {
        $this->setElasticsearchConnection($connection);

        $this->tokenizer = new WordBoundaries();

        $this->analysis = new Analysis();

        $this->properties = new MappingsProperties;
    }

    public function analysis(): AnalysisInterface
    {
        return $this->analysis;
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

    public function language(Language $language)
    {
        $builder = $language->builder($this->getElasticsearchConnection());

        $builder->alias($this->alias);

        return  $builder;
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

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function make(): Index
    {
        $defaultAnalyzer = $this->defaultAnalyzer();
        $defaultAnalyzer->addCharFilters($this->charFilters());
        $defaultAnalyzer->addFilters($this->filters());
        $defaultAnalyzer->setTokenizer($this->tokenizer);

        $mappings = $this->createMappings($defaultAnalyzer);

        $analyzers = $mappings->analyzers();

        $this->analysis()->addAnalyzers($analyzers);

        $settings = new Settings(
            primaryShards: $this->shards,
            replicaShards: $this->replicas,
            analysis: $this->analysis,
            configs: $this->config
        );

        $name = index_name($this->alias);

        return new Index($name, $settings, $mappings);
    }
}
