<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Exception;
use Sigmie\Support\Contracts\Collection as CollectionInterface;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\CharFilter\HTMLFilter;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Analysis\Tokenizers\NonLetter;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\Analyzers;
use Sigmie\Base\Contracts\CharFilter as ContractsCharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\Name;
use Sigmie\Base\Contracts\RawRepresentation;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Collection;

use function Sigmie\Helpers\ensure_collection;

class Analysis implements Analyzers
{
    protected CollectionInterface $analyzers;

    protected CollectionInterface $filter;

    protected CollectionInterface $charFilter;

    protected CollectionInterface $tokenizers;

    public function __construct(
        ?DefaultAnalyzer $defaultAnalyzer = null,
        array|CollectionInterface $analyzers = [],
    ) {
        $this->analyzers = ensure_collection($analyzers);

        if (!is_null($defaultAnalyzer)) {
            $this->analyzers->set('default', $defaultAnalyzer);
        }

        $this->initProps();
    }

    private function allAnalyzers(): CollectionInterface
    {
        return $this->analyzers;
    }

    public function tokenizers(): CollectionInterface
    {
        return $this->tokenizers;
    }

    public function updateTokenizers(array|CollectionInterface $tokenizers)
    {
        $this->tokenizers = ensure_collection($tokenizers);
    }

    public function updateFilters(array|CollectionInterface $filters)
    {
        $filters = ensure_collection($filters);

        $oldFilters = $this->filter->toArray();
        $newFilters = $filters->toArray();

        $this->filter = new Collection(array_merge($oldFilters, $newFilters));
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

    public function addAnalyzers(array|Collection $analyzers): void
    {
        $analyzers = ensure_collection($analyzers);

        $analyzers->forAll(function (Analyzer $analyzer) {
            $this->setAnalyzer($analyzer);
        });

        $this->filter = $this->filter->merge(
            $this->allAnalyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->filters())
                ->flatten()
                ->mapToDictionary(fn (TokenFilter $filter) => [$filter->name() => $filter])
        );

        $this->charFilter = $this->charFilter->merge(
            $this->allAnalyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->charFilters())
                ->flatten()
                ->mapToDictionary(fn (ContractsCharFilter $filter) => [$filter->name() => $filter])
        );

        $this->tokenizers = $this->tokenizers->merge(
            $this->allAnalyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->tokenizer())
                ->mapToDictionary(fn (Name $analyzer) => [$analyzer->name() => $analyzer])
        );
    }

    //TODO hmm don't like the naming
    public function setDefaultAnalyzer(Analyzer $analyzer): self
    {
        $this->analyzers['default'] = $analyzer;

        $this->filter = $this->filter->merge(
            $this->allAnalyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->filters())
                ->flatten()
                ->mapToDictionary(fn (TokenFilter $filter) => [$filter->name() => $filter])
        );

        $this->charFilter = $this->charFilter->merge(
            $this->allAnalyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->charFilters())
                ->flatten()
                ->mapToDictionary(fn (ContractsCharFilter $filter) => [$filter->name() => $filter])
        );

        $this->tokenizers = $this->tokenizers->merge(
            $this->allAnalyzers()
                ->map(fn (Analyzer $analyzer) => $analyzer->tokenizer())
                ->mapToDictionary(fn (Name $analyzer) => [$analyzer->name() => $analyzer])
        );

        return $this;
    }

    public function analyzers(): CollectionInterface
    {
        return $this->allAnalyzers();
    }

    public function createAnalyzer(string $name, Tokenizer $tokenizer,): Analyzer
    {
        $charFilters = new Collection($this->charFilters);

        $charFilterNames = $charFilters->map(function (ContractsCharFilter $filter) {
            return $filter->name();
        })->toArray();

        $this->analyzerName = $name;
        $this->tokenizers = $tokenizer;
        $analyzer = new Analyzer($name, $this->tokenizers, [
            ...$this->filters
        ], $charFilterNames);

        $this->analyzers[$name] = $analyzer;

        return $analyzer;
    }

    public function setAnalyzer(Analyzer $analyzer)
    {
        $this->analyzers[$analyzer->name()] = $analyzer;
    }

    public function addLanguageFilters(Language $language)
    {
        $filters = $language->filters()
            ->mapToDictionary(fn (TokenFilter $filter) => [$filter->name() => $filter]);

        $this->analyzers['default']->addFilters($filters);

        $this->updateFilters($filters);

        return $this;
    }

    public static function fromRaw(array $raw): static
    {
        $defaultAnalyzerName = 'default';
        $rawFilters = $raw['filter'];
        $rawChar = $raw['char_filter'];
        $rawTokenizer = $raw['tokenizer'];
        $filters = [];
        $charFilters = [];
        $tokenizers = [];

        foreach ($rawTokenizer as $name => $tokenizer) {

            if (isset($tokenizer['class']) === false) {
                //TODO create new raw filter
                continue;
            }

            $class = $tokenizer['class'];

            if (class_exists($class)) {
                $class = $tokenizer['class'];

                $tokenizerInstance = $class::fromRaw([$name => $tokenizer]);

                $tokenizers[$name] = $tokenizerInstance;
            }
        }

        foreach ($rawFilters as $name => $filter) {

            if (isset($filter['class']) === false) {
                //TODO create new raw filter
                continue;
            }

            $class = $filter['class'];

            if (class_exists($class)) {
                $class = $filter['class'];

                $filterInstance = $class::fromRaw([$name => $filter]);

                $filters[$name] = $filterInstance;
            }
        }

        foreach ($rawChar as $name => $filter) {

            if (isset($filter['class']) === false) {
                //TODO create new raw filter
                continue;
            }

            $class = $filter['class'];

            if (class_exists($class)) {
                $class = $filter['class'];

                $filterInstance = $class::fromRaw([$name => $filter]);

                $charFilters[$name] = $filterInstance;
            }
        }

        $analyzers = [];

        foreach ($raw['analyzer'] as $name => $analyzer) {
            $analyzerFilters = [];
            foreach ($analyzer['filter'] as $filterName) {
                $analyzerFilters[$filterName] = $filters[$filterName];
            }

            $analyzerCharFilters = [];
            foreach ($analyzer['char_filter'] as $filterName) {
                $analyzerCharFilters[$filterName] = match ($filterName) {
                    'html_strip' => new HTMLFilter,
                    default => $charFilters[$filterName]
                };
            }

            if (isset($tokenizers[$analyzer['tokenizer']])) {
                $tokenizer = $tokenizers[$analyzer['tokenizer']];
            } else {
                $tokenizer = match ($analyzer['tokenizer']) {
                    'whitespace' => new Whitespaces,
                    'letter' => new NonLetter,
                    default => throw new Exception("Tokenizer {$analyzer['tokenizer']} wasn't found")
                };
            }

            $analyzers[$name] = match ($name) {
                'default' => new DefaultAnalyzer($tokenizer, $analyzerFilters, $analyzerCharFilters),
                default => new Analyzer($name, $tokenizer, $analyzerFilters, $analyzerCharFilters)
            };
        }

        if (isset($analyzers[$defaultAnalyzerName])) {
            $defaultAnalyzer = $analyzers[$defaultAnalyzerName];
        } else {
            $defaultAnalyzer = new DefaultAnalyzer();
        }

        $analysis = new Analysis($defaultAnalyzer, $analyzers,);

        return $analysis;
    }

    private function initProps()
    {
        if (!isset($this->filter)) {
            $this->filter = (new Collection())->merge(
                $this->allAnalyzers()
                    ->map(fn (Analyzer $analyzer) => $analyzer->filters())
                    ->flatten()
                    ->mapToDictionary(fn (TokenFilter $filter) => [$filter->name() => $filter])
            );
        }

        if (!isset($this->charFilter)) {
            $this->charFilter = (new Collection())->merge(
                $this->allAnalyzers()
                    ->map(fn (Analyzer $analyzer) => $analyzer->charFilters())
                    ->flatten()
                    ->mapToDictionary(fn (ContractsCharFilter $filter) => [$filter->name() => $filter])
            );
        }

        if (!isset($this->tokenizers)) {
            $this->tokenizers = (new Collection())->merge(
                $this->allAnalyzers()
                    ->map(fn (Analyzer $analyzer) => $analyzer->tokenizer())
                    ->mapToDictionary(fn (Name $analyzer) => [$analyzer->name() => $analyzer])
            );
        }
    }

    public function toRaw(): array
    {
        $filter = $this->filters();

        $filter = $filter->mapToDictionary(function (TokenFilter $filter) {

            $value = $filter->value();
            $value['type'] = $filter->type();

            return [
                $filter->name() => $value
            ];
        })->toArray();

        $charFilters = $this->charFilters();

        $charFilters = $charFilters
            ->filter(fn ($filter) => $filter instanceof Configurable)
            ->mapToDictionary(function (Configurable $filter) {
                return [$filter->name() => $filter->config()];
            })->toArray();


        $tokenizer = $this->tokenizers();

        $tokenizer = $tokenizer
            ->filter(fn (Tokenizer $tokenizer) => $tokenizer instanceof Configurable)
            ->mapToDictionary(function (ConfigurableTokenizer $tokenizer) {
                return [$tokenizer->name() => $tokenizer->config()];
            })->toArray();

        $analyzers = $this->allAnalyzers();

        $analyzers = $this->analyzers
            ->mapToDictionary(function (Analyzer $analyzer) {
                return [$analyzer->name() => $analyzer->toRaw()];
            })->toArray();

        $result = [
            'analyzer' => $analyzers,
            'filter' => $filter,
            'char_filter' => $charFilters,
            'tokenizer' => $tokenizer
        ];

        return $result;
    }
}
