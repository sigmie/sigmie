<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis;

use Exception;
use Sigmie\Index\Analysis\CharFilter\CharFilter;
use Sigmie\Index\Analysis\CharFilter\Generic as GenericCharFilter;
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

    private bool $restored = false;

    public function __construct(
        array $analyzers = [],
        array $normalizers = []
    ) {
        $this->analyzers = new Collection;
        $this->normalizers = new Collection;
        $this->tokenizers = new Collection;
        $this->filters = new Collection;
        $this->charFilters = new Collection;

        foreach ($normalizers as $normalizer) {
            $this->addNormalizer($normalizer);
        }

        $this->addAnalyzers($analyzers);
    }

    public function registerFieldComponents(AnalysisInterface $target): void
    {
        $tokenizers = $this->tokenizers();
        foreach ($tokenizers as $tokenizer) {
            if ($target->hasTokenizer($tokenizer->name()) && $target->tokenizers()[$tokenizer->name()]->toRaw() !== $tokenizer->toRaw()) {
                throw new Exception(sprintf('Tokenizer name `%s` already exists.', $tokenizer->name()));
            }
        }

        foreach ($this->filters as $filter) {
            if ($target->hasFilter($filter->name()) && $target->filters()[$filter->name()]->toRaw() !== $filter->toRaw()) {
                throw new Exception(sprintf('Token filter `%s` already exists.', $filter->name()));
            }
        }

        foreach ($this->charFilters as $filter) {
            if ($target->hasCharFilter($filter->name()) && $target->charFilters()[$filter->name()]->toRaw() !== $filter->toRaw()) {
                throw new Exception(sprintf('Char filter name `%s` already exists.', $filter->name()));
            }
        }

        $target->addFilters($this->filters());
        $target->addCharFilters($this->charFilters());
        $target->addTokenizers($tokenizers);
        $target->addAnalyzers($this->analyzers());
        foreach ($this->normalizers as $normalizer) {
            $target->addNormalizer($normalizer);
        }
    }

    public function __clone(): void
    {
        $components = [];
        $copyComponents = static function (Collection $collection) use (&$components): Collection {
            return new Collection(array_map(static function (object $component) use (&$components): object {
                return $components[spl_object_id($component)] ??= clone $component;
            }, $collection->toArray()));
        };

        $this->filters = $copyComponents($this->filters);
        $this->charFilters = $copyComponents($this->charFilters);
        $this->tokenizers = $copyComponents($this->tokenizers);

        // A registry entry and its pipeline stages must still refer to the same object.
        $sharedStages = static fn (array $stages): array => array_values(array_intersect_key(
            $components,
            array_flip(array_map(spl_object_id(...), $stages)),
        ));

        $analyzers = $this->analyzers->toArray();
        $this->analyzers = $copyComponents($this->analyzers);
        foreach ($analyzers as $name => $analyzer) {
            if (! $analyzer instanceof CustomAnalyzerInterface) {
                continue;
            }

            $copy = $this->analyzers[$name];
            $copy->addFilters($sharedStages($analyzer->filters()));
            $copy->addCharFilters($sharedStages($analyzer->charFilters()));

            $tokenizer = $analyzer->tokenizer();
            if ($tokenizer !== null && isset($components[spl_object_id($tokenizer)])) {
                $copy->setTokenizer($components[spl_object_id($tokenizer)]);
            }
        }

        $normalizers = $this->normalizers->toArray();
        $this->normalizers = $copyComponents($this->normalizers);
        foreach ($normalizers as $name => $normalizer) {
            $copy = $this->normalizers[$name];
            $copy->addFilters($sharedStages($normalizer->filters()));
            $copy->addCharFilters($sharedStages($normalizer->charFilters()));
        }
    }

    public function tokenizers(): array
    {
        $tokenizers = $this->tokenizers->toArray();
        foreach ($this->analyzers as $analyzer) {
            if ($analyzer instanceof CustomAnalyzerInterface && ($tokenizer = $analyzer->tokenizer()) !== null) {
                $tokenizers[$tokenizer->name()] = $tokenizer;
            }
        }

        return $tokenizers;
    }

    public function addTokenizers(array $tokenizers): void
    {
        foreach ($tokenizers as $tokenizer) {
            $this->addTokenizer($tokenizer);
        }
    }

    public function addTokenizer(TokenizerInterface $tokenizer): void
    {
        $this->tokenizers->set($tokenizer->name(), $tokenizer);

        foreach ($this->analyzers as $analyzer) {
            if ($analyzer instanceof CustomAnalyzerInterface && $analyzer->tokenizer()?->name() === $tokenizer->name()) {
                $analyzer->setTokenizer($tokenizer);
            }
        }
    }

    public function addFilters(array $filters): void
    {
        $filters = array_filter($filters, static fn ($filter): bool => ! $filter instanceof FilterReference);
        $this->filters = $this->filters->mergeNamed($this->keyedByName($filters));
    }

    public function addCharFilters(array $charFilters): void
    {
        $charFilters = array_filter($charFilters, static fn ($filter): bool => ! $filter instanceof FilterReference);
        $this->charFilters = $this->charFilters->mergeNamed($this->keyedByName($charFilters));
    }

    public function filters(): array
    {
        return $this->filters->toArray();
    }

    public function charFilters(): array
    {
        return $this->charFilters->toArray();
    }

    public function defaultAnalyzer(): AnalyzerInterface
    {
        if ($this->restored && ! $this->hasAnalyzer('default')) {
            return new BuiltInAnalyzer('default', ['type' => 'standard']);
        }

        $this->analyzers['default'] ?? $this->analyzers['default'] = new DefaultAnalyzer;

        return $this->analyzers['default'];
    }

    public function hasTokenizer(string $tokenizerName): bool
    {
        return isset($this->tokenizers()[$tokenizerName]);
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

    public function normalizers(): array
    {
        return $this->normalizers->toArray();
    }

    public function addAnalyzer(AnalyzerInterface $analyzer): void
    {
        $this->analyzers->set($analyzer->name(), $analyzer);

        if ($analyzer instanceof CustomAnalyzerInterface) {
            $this->addCharFilters($analyzer->charFilters());
            $this->addFilters($analyzer->filters());
            $tokenizer = $analyzer->tokenizer();
            if ($tokenizer !== null) {
                $this->tokenizers->set($tokenizer->name(), $tokenizer);
            }
        }
    }

    public function addNormalizer(NormalizerInterface $normalizer): void
    {
        $this->normalizers->set($normalizer->name(), $normalizer);

        $this->addCharFilters($normalizer->charFilters());

        foreach ($normalizer->filters() as $filter) {
            if ($filter instanceof TokenFilterInterface) {
                $this->addFilters([$filter->name() => $filter]);
            }
        }
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
            $class = CharFilter::$map[$filter['type']] ?? GenericCharFilter::class;
            $charFilters[$name] = $class::fromRaw([$name => $filter]);
        }

        $analyzers = [];

        foreach ($raw['analyzer'] ?? [] as $name => $analyzer) {
            if (($analyzer['type'] ?? 'custom') !== 'custom') {
                $analyzers[$name] = new BuiltInAnalyzer((string) $name, $analyzer);

                continue;
            }

            $analyzers[$name] = Analyzer::create(
                [$name => $analyzer],
                $charFilters,
                $filters,
                $tokenizers
            );
        }

        $normalizers = [];

        foreach ($raw['normalizer'] ?? [] as $name => $normalizer) {
            $normalizers[$name] = Normalizer::create(
                [$name => $normalizer],
                $charFilters,
                $filters
            );
        }

        $analysis = new static($analyzers, $normalizers);
        $analysis->restored = true;
        $analysis->addTokenizers($tokenizers);
        $analysis->addFilters($filters);
        $analysis->addCharFilters($charFilters);

        return $analysis;
    }

    public function toRaw(): array
    {
        $pipelines = new self($this->analyzers(), $this->normalizers->toArray());
        $filter = (new Collection($this->filters() + $pipelines->filters()))
            ->mapToDictionary(
                fn (TokenFilterInterface $tokenFilter): array => $tokenFilter->toRaw()
            )->toArray();

        $charFilters = (new Collection($this->charFilters() + $pipelines->charFilters()))
            ->mapToDictionary(fn (CharFIlterInterface $charFilter): array => $charFilter->toRaw())
            ->toArray();

        $tokenizer = (new Collection($this->tokenizers()))
            ->mapToDictionary(fn (TokenizerInterface $tokenizer): array => $tokenizer->toRaw())
            ->toArray();

        $analyzers = $this->analyzers
            ->mapToDictionary(fn (AnalyzerInterface $analyzer): array => $analyzer->toRaw())
            ->toArray();

        $normalizers = $this->normalizers
            ->mapToDictionary(fn (NormalizerInterface $normalizer): array => $normalizer->toRaw())
            ->toArray();

        return [
            'analyzer' => $this->keepNames($analyzers),
            'filter' => $this->keepNames($filter),
            'char_filter' => $this->keepNames($charFilters),
            'tokenizer' => $this->keepNames($tokenizer),
            'normalizer' => $this->keepNames($normalizers),
        ];
    }

    /**
     * A list of components is keyed 0, 1, ... The name lives on the component.
     *
     * @param  array<string|int, mixed>  $components
     * @return array<string|int, mixed>
     */
    private function keyedByName(array $components): array
    {
        $named = [];

        foreach ($components as $key => $component) {
            $name = is_object($component) && method_exists($component, 'name')
                ? $component->name()
                : $key;

            $named[$name] = $component;
        }

        return $named;
    }

    /**
     * A component named "0" is the only key, so the map is a PHP list.
     *
     * @param  array<string|int, mixed>  $map
     */
    private function keepNames(array $map): array|\stdClass
    {
        if ($map !== [] && array_is_list($map)) {
            return (object) $map;
        }

        return $map;
    }
}
