<?php


declare(strict_types=1);

namespace Sigmie\Support\Update;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\CharFilter\HTMLFilter;
use Sigmie\Base\Analysis\CharFilter\MappingFilter;
use Sigmie\Base\Analysis\CharFilter\PatternFilter;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Contracts\Analysis;
use Sigmie\Support\Shared\CharFilters;
use Sigmie\Support\Shared\Filters;
use Sigmie\Support\Shared\Tokenizer;
use Sigmie\Base\Contracts\Analyzer as AnalyzerInterface;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Contracts\Tokenizer as TokenizerInterface;
use function Sigmie\Helpers\ensure_collection;
use function Sigmie\Helpers\named_collection;
use Sigmie\Support\Analysis\AnalyzerUpdate;
use Sigmie\Support\Analysis\Tokenizer\TokenizerBuilder as TokenizerBuilder;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;
use Sigmie\Support\Shared\Mappings;
use Sigmie\Support\Shared\Replicas;
use Sigmie\Support\Shared\Shards;
use Sigmie\Support\Update\TokenizerBuilder as UpdateTokenizerBuilder;

class Update
{
    use Mappings, Filters, Tokenizer, CharFilters, Shards, Replicas;

    protected CollectionInterface $analyzerUpdateBuilders;

    public function __construct(protected Analysis $analysis)
    {
        $this->analyzerUpdateBuilders = new Collection();
        $this->filters = new Collection();
        $this->charFilters = new Collection();
        $this->tokenizer = $analysis->defaultAnalyzer()->tokenizer();
    }

    public function analysis(): Analysis
    {
        return $this->analysis;
    }

    public function analyzer(string $name): AnalyzerUpdate
    {
        $analyzer = $this->analysis->analyzers()[$name] ?? new Analyzer($name);

        $this->analysis->addAnalyzers([$name => $analyzer]);

        $builder = new AnalyzerUpdate($this->analysis, $analyzer);

        $this->analyzerUpdateBuilders[$name] = $builder;

        return $builder;
    }

    public function tokenizerValue()
    {
        return $this->tokenizer;
    }

    public function analyzers(): CollectionInterface
    {
        return $this->analyzerUpdateBuilders->map(function (AnalyzerUpdate $analyzerUpdate) {
            return $analyzerUpdate->analyzer();
        });
    }

    public function tokenizeOn(): UpdateTokenizerBuilder
    {
        return new UpdateTokenizerBuilder($this);
    }

    public function numberOfShards(): int
    {
        return $this->shards;
    }

    public function numberOfReplicas(): int
    {
        return $this->replicas;
    }

    public function mappings(): MappingsInterface
    {
        return $this->createMappings($this->analysis->analyzers()['default']);
    }

    public function filter(string $name, array $values): void
    {
        $filter = $this->analysis->filters()[$name];

        $filter->settings($values);

        $this->filters[$name] = $filter;
    }
}
