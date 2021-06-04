<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Exception;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Tokenizers\NonLetter;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\CharFilter as ContractsCharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\RawRepresentation;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Collection;

class Analysis
{
    public function __construct(
        protected Analyzer $defaultAnalyzer,
        protected array $tokenizers = [],
        protected array $analyzers = [],
        protected array $filters = [],
        protected array $charFilters = [],
    ) {
    }

    public function tokenizer(): array
    {
        return $this->tokenizers;
    }

    public function filters(): array
    {
        return $this->filters;
    }

    public function charFilters(): array
    {
        return $this->charFilters;
    }

    public function defaultAnalyzer(): Analyzer
    {
        return $this->defaultAnalyzer;
    }

    public function setDefaultAnalyzer(Analyzer $analyzer): self
    {
        $this->addAnalyzer($analyzer);
        $this->defaultAnalyzer = $analyzer;

        return $this;
    }

    public function analyzers(): array
    {
        return $this->analyzers;
    }

    public function createAnalyzer(string $name, Tokenizer $tokenizer,): Analyzer
    {
        $charFilters = new Collection($this->charFilters);

        $charFilterNames = $charFilters->map(function (ContractsCharFilter $filter) {
            return $filter->name();
        })->toArray();

        $this->analyzerName = $name;
        $this->tokenizer = $tokenizer;
        $analyzer = new Analyzer($name, $this->tokenizer, [
            ...$this->filters
        ], $charFilterNames);

        // if (!isset($this->defaultAnalyzer)) {
        $this->defaultAnalyzer = $analyzer;
        // }

        $this->analyzers[$name] = $analyzer;

        return $this->defaultAnalyzer;
    }

    public function addAnalyzer(Analyzer $analyzer)
    {
        if (!isset($this->defaultAnalyzer)) {
            $this->defaultAnalyzer = $analyzer;
        }

        $this->analyzers[$analyzer->name()] = $analyzer;
    }

    public function addLanguageFilters(Language $language)
    {
        $this->filters = [
            ...$this->filters,
            $language->stopwords(),
            ...$language->stemmers()
        ];

        return $this;
    }

    public static function fromRaw(array $data, string $defaultAnalyzerName): static
    {
        $rawFilters = $data['filter'];
        $rawChar = $data['char_filter'];
        $rawTokenizer = $data['tokenizer'];
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

                $tokenizerInstance = $class::fromRaw($tokenizer);

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

                $filterInstance = $class::fromRaw($filter);
                $filterInstance->setName($name);
                $filterInstance->setPriority((int)$filter['priority']);

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

                $filterInstance = $class::fromRaw($filter);

                $charFilters[$name] = $filterInstance;
            }
        }

        $analyzers = [];

        foreach ($data['analyzer'] as $name => $analyzer) {
            $analyzerFilters = [];
            foreach ($analyzer['filter'] as $filterName) {
                $analyzerFilters[] = $filters[$filterName];
            }

            $analyzerCharFilters = [];
            foreach ($analyzer['char_filter'] as $filterName) {
                $analyzerCharFilters[] = $charFilters[$filterName];
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

            [$prefix, $res] = preg_split("/_/", $name);

            $analyzers[$name] = new Analyzer($prefix, $tokenizer, $analyzerFilters, $charFilters);
        }

        $defaultAnalyzer = $analyzers[$defaultAnalyzerName];

        $analysis = new Analysis(
            $defaultAnalyzer,
            [$tokenizer],
            $analyzers,
            array_values($filters),
            array_values($charFilters),
        );

        return $analysis;
    }

    public function toRaw(): array
    {
        $filter = new Collection($this->filters);

        $filter = $filter->mapToDictionary(function (TokenFilter $filter) {

            $value = $filter->value();
            $value['type'] = $filter->type();

            return [
                $filter->name() => $value
            ];
        })->toArray();

        $charFilters = new Collection($this->charFilters);

        $charFilters = $charFilters
            ->filter(fn ($filter) => $filter instanceof Configurable)
            ->mapToDictionary(function (Configurable $filter) {
                return [$filter->name() => $filter->config()];
            })->toArray();

        $tokenizer = new Collection($this->tokenizers);

        $tokenizer = $tokenizer
            ->filter(fn (Tokenizer $tokenizer) => $tokenizer instanceof Configurable)
            ->mapToDictionary(function (ConfigurableTokenizer $tokenizer) {
                return [$tokenizer->name() => $tokenizer->config()];
            })->toArray();

        $analyzers = new Collection($this->analyzers);

        $analyzers = $analyzers
            ->mapToDictionary(function (Analyzer $analyzer) {
                return [$analyzer->name() => $analyzer->raw()];
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
