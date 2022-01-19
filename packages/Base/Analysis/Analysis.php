<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\CharFilter\CharFilter;
use Sigmie\Base\Analysis\TokenFilter\TokenFilter;
use Sigmie\Base\Analysis\Tokenizers\Tokenizer;
use Sigmie\Base\Contracts\Analysis as AnalysisInterface;
use Sigmie\Base\Contracts\Analyzers;
use Sigmie\Base\Contracts\CharFilter as ContractsCharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Contracts\Name;
use Sigmie\Base\Contracts\Raw;
use Sigmie\Base\Contracts\TokenFilter as TokenFilterInterface;
use Sigmie\Base\Contracts\Tokenizer as TokenizerInterface;
use function Sigmie\Helpers\ensure_collection;

use Sigmie\Support\Contracts\Collection as CollectionInterface;

class Analysis implements AnalysisInterface, Analyzers, Raw
{
    protected CollectionInterface $analyzers;

    protected CollectionInterface $filters;

    protected CollectionInterface $charFilter;

    protected CollectionInterface $tokenizers;

    public function __construct(
        array|CollectionInterface $analyzers = [],
    ) {
        $this->analyzers = ensure_collection($analyzers);

        $this->filters = $this->analyzers()
            ->map(fn (Analyzer $analyzer) => $analyzer->filters())
            ->flatten()
            ->mapToDictionary(fn (TokenFilterInterface $filter) => [$filter->name() => $filter]);

        $this->charFilter = $this->analyzers()
            ->map(fn (Analyzer $analyzer) => $analyzer->charFilters())
            ->flatten()
            ->mapToDictionary(fn (ContractsCharFilter $filter) => [$filter->name() => $filter]);

        $this->tokenizers = $this->analyzers()
            ->map(fn (Analyzer $analyzer) => $analyzer->tokenizer())
            ->filter(fn ($tokenizer) => !is_null($tokenizer))
            ->mapToDictionary(fn (Name $analyzer) => [$analyzer->name() => $analyzer]);
    }

    public function tokenizers(): CollectionInterface
    {
        return $this->tokenizers;
    }

    public function updateAnalyzers(array|CollectionInterface $analyzers): void
    {
        $analyzers = ensure_collection($analyzers);

        $this->analyzers = $this->analyzers->merge($analyzers);
    }

    public function updateTokenizers(array|CollectionInterface $tokenizers): void
    {
        $tokenizers = ensure_collection($tokenizers);

        $this->tokenizers = $this->tokenizers->merge($tokenizers);
    }

    public function updateFilters(array|CollectionInterface $filters): void
    {
        $filters = ensure_collection($filters);

        $this->filters = $this->filters->merge($filters);
    }

    public function updateCharFilters(array|CollectionInterface $charFilters): void
    {
        $charFilters = ensure_collection($charFilters);

        $this->charFilter = $this->charFilter->merge($charFilters);
    }

    public function filters(): CollectionInterface
    {
        return $this->filters;
    }

    public function charFilters(): CollectionInterface
    {
        return $this->charFilter;
    }

    public function defaultAnalyzer(): DefaultAnalyzer
    {
        $this->analyzers['default'] ?? $this->analyzers['default'] = new DefaultAnalyzer();

        return $this->analyzers['default'];
    }

    public function hasTokenizer(string $tokenizerName): bool
    {
        return $this->tokenizers->hasKey($tokenizerName);
    }

    public function hasFilter(string $filterName): bool
    {
        return $this->filters->hasKey($filterName);
    }

    public function hasAnalyzer(string $analyzerName): bool
    {
        return $this->analyzers->hasKey($analyzerName);
    }

    public function hasCharFilter(string $charFilterName): bool
    {
        return $this->charFilter->hasKey($charFilterName);
    }

    public function addAnalyzers(array|CollectionInterface $analyzers): void
    {
        $analyzers = ensure_collection($analyzers);

        $analyzers->each(function (Analyzer $analyzer) {
            $this->setAnalyzer($analyzer);

            $this->filters = $this->filters->merge($analyzer->filters());
            $this->charFilter = $this->charFilter->merge($analyzer->charFilters());
            $this->tokenizers = $this->tokenizers->set($analyzer->tokenizer()->name(), $analyzer->tokenizer());
        });
    }

    public function analyzers(): CollectionInterface
    {
        return $this->analyzers;
    }

    public function setAnalyzer(Analyzer $analyzer): void
    {
        $this->analyzers[$analyzer->name()] = $analyzer;
    }

    public static function fromRaw(array $raw): static
    {
        $tokenizers = [];

        foreach ($raw['tokenizer'] as $name => $tokenizer) {
            $tokenizers[$name] = Tokenizer::fromRaw([$name => $tokenizer]);
        }

        $filters = [];

        foreach ($raw['filter'] as $name => $filter) {
            $filters[$name] = TokenFilter::fromRaw([$name => $filter]);
        }

        $charFilters = [];

        foreach ($raw['char_filter'] as $name => $filter) {
            $charFilters[$name] = CharFilter::fromRaw([$name => $filter]);
        }

        $analyzers = [];

        foreach ($raw['analyzer'] as $name => $analyzer) {
            $analyzers[$name] = Analyzer::create(
                [$name => $analyzer],
                $charFilters,
                $filters,
                $tokenizers
            );
        }

        return new static($analyzers);
    }

    public function toRaw(): array
    {
        $filter = $this->filters()
            ->mapToDictionary(
                fn (TokenFilterInterface $tokenFilter) => $tokenFilter->toRaw()
            )->toArray();

        $charFilters = $this->charFilters()
            ->filter(fn ($filter) => $filter instanceof Configurable)
            ->mapToDictionary(fn (Configurable $filter) => $filter->toRaw())
            ->toArray();

        $tokenizer = $this->tokenizers()
            ->filter(fn (TokenizerInterface $tokenizer) => $tokenizer instanceof Configurable)
            ->mapToDictionary(fn (ConfigurableTokenizer $tokenizer) => $tokenizer->toRaw())
            ->toArray();

        $analyzers = $this->analyzers()
            ->mapToDictionary(fn (Analyzer $analyzer) => $analyzer->toRaw())
            ->toArray();

        return [
            'analyzer' => $analyzers,
            'filter' => $filter,
            'char_filter' => $charFilters,
            'tokenizer' => $tokenizer,
        ];
    }
}
