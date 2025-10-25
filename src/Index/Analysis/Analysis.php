<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis;

use Sigmie\Index\Analysis\CharFilter\CharFilter;
use Sigmie\Index\Analysis\Normalizer\Normalizer;
use Sigmie\Index\Analysis\TokenFilter\TokenFilter;
use Sigmie\Index\Analysis\Tokenizers\Tokenizer;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\Analyzer as AnalyzerInterface;
use Sigmie\Index\Contracts\CharFilter as CharFIlterInterface;
use Sigmie\Index\Contracts\CustomAnalyzer as CustomAnalyzerInterface;
use Sigmie\Index\Contracts\Normalizer as NormalizerInterface;
use Sigmie\Index\Contracts\TokenFilter as TokenFilterInterface;
use Sigmie\Index\Contracts\Tokenizer as TokenizerInterface;
use Sigmie\Shared\Collection;

class Analysis implements AnalysisInterface
{
    protected Collection $filters;

    protected Collection $charFilters;

    protected Collection $tokenizers;

    protected Collection $normalizers;

    protected Collection $analyzers;

    public function __construct(
        array $analyzers = [],
        array $normalizers = []
    ) {
        $this->analyzers = new Collection($analyzers);
        $this->normalizers = new Collection($normalizers);

        $this->filters = $this->analyzers
            ->filter(fn (AnalyzerInterface $analyzer): bool => $analyzer instanceof CustomAnalyzerInterface)
            ->map(fn (CustomAnalyzerInterface $analyzer): array => $analyzer->filters())
            ->flatten()
            ->mapToDictionary(fn (TokenFilterInterface $filter) => [$filter->name() => $filter]);

        $this->charFilters = $this->analyzers
            ->filter(fn (AnalyzerInterface $analyzer): bool => $analyzer instanceof CustomAnalyzerInterface)
            ->map(fn (CustomAnalyzerInterface $analyzer): array => $analyzer->charFilters())
            ->flatten()
            ->mapToDictionary(fn (CharFIlterInterface $filter) => [$filter->name() => $filter]);

        $this->charFilters = $this->normalizers
            ->map(fn (Normalizer $analyzer): array => $analyzer->charFilters())
            ->flatten()
            ->mapToDictionary(fn (CharFIlterInterface $filter) => [$filter->name() => $filter])
            ->merge($this->charFilters);

        $this->tokenizers = $this->analyzers
            ->filter(fn (AnalyzerInterface $analyzer): bool => $analyzer instanceof CustomAnalyzerInterface)
            ->map(fn (CustomAnalyzerInterface $analyzer): ?\Sigmie\Index\Contracts\Tokenizer => $analyzer->tokenizer())
            ->filter(fn ($tokenizer): bool => ! is_null($tokenizer))
            ->mapToDictionary(fn (TokenizerInterface $tokenizer) => [$tokenizer->name() => $tokenizer]);
    }

    public function tokenizers(): array
    {
        return $this->tokenizers->toArray();
    }

    public function addTokenizers(array $tokenizers): void
    {
        $this->tokenizers->merge($tokenizers);
    }

    public function addTokenizer(TokenizerInterface $tokenizer): void
    {
        $this->tokenizers->set($tokenizer->name(), $tokenizer);
    }

    public function addFilters(array $filters): void
    {
        $this->filters = $this->filters->merge($filters);
    }

    public function addCharFilters(array $charFilters): void
    {
        $this->charFilters = $this->charFilters->merge($charFilters);
    }

    public function filters(): array
    {
        return $this->filters->toArray();
    }

    public function charFilters(): array
    {
        return $this->charFilters->toArray();
    }

    public function defaultAnalyzer(): DefaultAnalyzer
    {
        $this->analyzers['default'] ?? $this->analyzers['default'] = new DefaultAnalyzer;

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
        return $this->charFilters->hasKey($charFilterName);
    }

    public function addAnalyzers(array $analyzers): void
    {
        $newAnalyzers = new Collection($analyzers);

        $newAnalyzers->each(function (AnalyzerInterface $analyzer): void {
            $this->addAnalyzer($analyzer);
        });
    }

    public function analyzers(): array
    {
        return $this->analyzers->toArray();
    }

    public function addAnalyzer(AnalyzerInterface $analyzer): void
    {
        $this->analyzers->set($analyzer->name(), $analyzer);

        if ($analyzer instanceof CustomAnalyzerInterface) {
            $this->addCharFilters($analyzer->charFilters());
            $this->addFilters($analyzer->filters());
            $this->addTokenizer($analyzer->tokenizer());
        }
    }

    public function addNormalizer(NormalizerInterface $normalizer): void
    {
        $this->normalizers->set($normalizer->name(), $normalizer);

        $this->addCharFilters($normalizer->charFilters());
        // $this->addFilters($analyzer->filters());
    }

    public static function fromRaw(array $raw): static
    {
        $tokenizers = [];

        foreach ($raw['tokenizer'] ?? [] as $name => $tokenizer) {
            $tokenizers[$name] = Tokenizer::fromRaw([$name => $tokenizer]);
        }

        $filters = [];

        foreach ($raw['filter'] ?? [] as $name => $filter) {
            $filters[$name] = TokenFilter::fromRaw([$name => $filter]);
        }

        $charFilters = [];

        foreach ($raw['char_filter'] ?? [] as $name => $filter) {
            $charFilters[$name] = CharFilter::fromRaw([$name => $filter]);
        }

        $analyzers = [];

        foreach ($raw['analyzer'] ?? [] as $name => $analyzer) {
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
        $filter = $this->filters
            ->mapToDictionary(
                fn (TokenFilterInterface $tokenFilter): array => $tokenFilter->toRaw()
            )->toArray();

        $charFilters = $this->charFilters
            ->mapToDictionary(fn (CharFIlterInterface $charFilter): array => $charFilter->toRaw())
            ->toArray();

        $tokenizer = $this->tokenizers
            ->mapToDictionary(fn (TokenizerInterface $tokenizer): array => $tokenizer->toRaw())
            ->toArray();

        $analyzers = $this->analyzers
            ->mapToDictionary(fn (AnalyzerInterface $analyzer): array => $analyzer->toRaw())
            ->toArray();

        $normalizers = $this->normalizers
            ->mapToDictionary(fn (NormalizerInterface $normalizer): array => $normalizer->toRaw())
            ->toArray();

        return [
            'analyzer' => $analyzers,
            'filter' => $filter,
            'char_filter' => $charFilters,
            'tokenizer' => $tokenizer,
            'normalizer' => $normalizers,
        ];
    }
}
