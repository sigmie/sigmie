<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Exception;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\CharFilter\CharFilter;
use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Analysis\TokenFilter\TokenFilter;
use Sigmie\Base\Analysis\Tokenizers\NonLetter;
use Sigmie\Base\Analysis\Tokenizers\Tokenizer;
use Sigmie\Base\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\Contracts\Analysis as AnalysisInterface;
use Sigmie\Base\Contracts\Analyzers;
use Sigmie\Base\Contracts\CharFilter as ContractsCharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\Name;
use Sigmie\Base\Contracts\Raw;
use Sigmie\Base\Contracts\TokenFilter as TokenFilterInterface;
use Sigmie\Base\Contracts\Tokenizer as TokenizerInterface;
use function Sigmie\Helpers\ensure_collection;
use Sigmie\Support\Collection;

use Sigmie\Support\Contracts\Collection as CollectionInterface;

class Analysis implements Analyzers, Raw, AnalysisInterface
{
    protected CollectionInterface $analyzers;

    protected CollectionInterface $filter;

    protected CollectionInterface $charFilter;

    protected CollectionInterface $tokenizers;

    public function __construct(
        array|CollectionInterface $analyzers = [],
    ) {
        $this->analyzers = ensure_collection($analyzers);

        $this->analyzers['default'] ?? $this->analyzers['default'] = new DefaultAnalyzer();

        $this->initProps();
    }

    public function tokenizers(): CollectionInterface
    {
        return $this->tokenizers;
    }

    public function updateAnalyzers(array|CollectionInterface $analyzers): void
    {
        $analyzers = ensure_collection($analyzers);

        $oldAnalyzers = $this->analyzers->toArray();
        $newAnalyzers = $analyzers->toArray();

        $this->analyzers = new Collection(array_merge($oldAnalyzers, $newAnalyzers));
    }

    public function updateTokenizers(array|CollectionInterface $tokenizers): void
    {
        $tokenizers = ensure_collection($tokenizers);

        $oldTokenizers = $this->tokenizers->toArray();
        $newTokenizers = $tokenizers->toArray();

        $this->tokenizers = new Collection(array_merge($oldTokenizers, $newTokenizers));
    }

    public function updateFilters(array|CollectionInterface $filters): void
    {
        $filters = ensure_collection($filters);

        $oldFilters = $this->filter->toArray();
        $newFilters = $filters->toArray();

        $this->filter = new Collection(array_merge($oldFilters, $newFilters));
    }

    public function updateCharFilters(array|CollectionInterface $charFilter): void
    {
        $charFilter = ensure_collection($charFilter);

        $oldFilters = $this->charFilter->toArray();
        $newFilters = $charFilter->toArray();

        $this->charFilter = new Collection(array_merge($oldFilters, $newFilters));
    }

    public function filters(): CollectionInterface
    {
        return $this->filter;
    }

    public function charFilters(): CollectionInterface
    {
        return $this->charFilter;
    }

    public function defaultAnalyzer(): DefaultAnalyzer
    {
        return $this->analyzers['default'];
    }

    public function hasTokenizer(string $tokenizerName): bool
    {
        return $this->tokenizers->hasKey($tokenizerName);
    }

    public function hasFilter(string $filterName): bool
    {
        return $this->filter->hasKey($filterName);
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
        });

        $this->filter = $this->filter->merge(
            $this->analyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->filters())
                ->flatten()
                ->mapToDictionary(fn (TokenFilterInterface $filter) => [$filter->name() => $filter])
        );

        $this->charFilter = $this->charFilter->merge(
            $this->analyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->charFilters())
                ->flatten()
                ->mapToDictionary(fn (ContractsCharFilter $filter) => [$filter->name() => $filter])
        );

        $this->tokenizers = $this->tokenizers->merge(
            $this->analyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->tokenizer())
                ->mapToDictionary(fn (Name $analyzer) => [$analyzer->name() => $analyzer])
        );
    }

    //TODO hmm don't like the naming
    public function setDefaultAnalyzer(Analyzer $analyzer): self
    {
        $this->analyzers['default'] = $analyzer;

        $this->filter = $this->filter->merge(
            $this->analyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->filters())
                ->flatten()
                ->mapToDictionary(fn (TokenFilterInterface $filter) => [$filter->name() => $filter])
        );

        $this->charFilter = $this->charFilter->merge(
            $this->analyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->charFilters())
                ->flatten()
                ->mapToDictionary(fn (ContractsCharFilter $filter) => [$filter->name() => $filter])
        );

        $this->tokenizers = $this->tokenizers->merge(
            $this->analyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->tokenizer())
                ->mapToDictionary(fn (Name $analyzer) => [$analyzer->name() => $analyzer])
        );

        return $this;
    }

    public function analyzers(): CollectionInterface
    {
        return $this->analyzers;
    }

    public function setAnalyzer(Analyzer $analyzer)
    {
        $this->analyzers[$analyzer->name()] = $analyzer;
    }


    public function addLanguageFilters(Language $language): static
    {
        $filters = $language->filters()
            ->mapToDictionary(fn (TokenFilterInterface $filter) => [$filter->name() => $filter]);

        $this->analyzers['default']->addFilters($filters);

        $this->updateFilters($filters);

        return $this;
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

        return new Analysis($analyzers);
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
            'tokenizer' => $tokenizer
        ];
    }

    private function initProps()
    {
        if (!isset($this->filter)) {
            $this->filter = (new Collection())->merge(
                $this->analyzers()
                    ->map(fn (Analyzer $analyzer) => $analyzer->filters())
                    ->flatten()
                    ->mapToDictionary(fn (TokenFilterInterface $filter) => [$filter->name() => $filter])
            );
        }

        if (!isset($this->charFilter)) {
            $this->charFilter = (new Collection())->merge(
                $this->analyzers()
                    ->map(fn (Analyzer $analyzer) => $analyzer->charFilters())
                    ->flatten()
                    ->mapToDictionary(fn (ContractsCharFilter $filter) => [$filter->name() => $filter])
            );
        }

        if (!isset($this->tokenizers)) {
            $this->tokenizers = (new Collection())->merge(
                $this->analyzers()
                    ->map(fn (Analyzer $analyzer) => $analyzer->tokenizer())
                    ->mapToDictionary(fn (Name $analyzer) => [$analyzer->name() => $analyzer])
            );
        }
    }
}
