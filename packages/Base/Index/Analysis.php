<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Exception;
use Sigmie\Support\Contracts\Collection as CollectionInterface;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\DefaultAnalyzer;
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

use function Sigmie\Helpers\ensure_collection;

class Analysis
{
    protected CollectionInterface $analyzers;

    public function __construct(
        protected DefaultAnalyzer $defaultAnalyzer,
        array|CollectionInterface $analyzers = [],
    ) {
        $this->analyzers = ensure_collection($analyzers);
    }

    private function allAnalyzers(): CollectionInterface
    {
        $analyzers = $this->analyzers;

        $analyzers->add($this->defaultAnalyzer);

        return $analyzers;
    }

    public function tokenizers(): CollectionInterface
    {
        return $this->allAnalyzers()
            ->map(fn (Analyzer $analyzer) => $analyzer->tokenizer());
    }

    public function filters(): CollectionInterface
    {
        return $this->allAnalyzers()
            ->map(fn (Analyzer $analyzer) => $analyzer->tokenFilters())
            ->flatten();
    }

    public function charFilters(): CollectionInterface
    {
        return $this->allAnalyzers()
            ->map(fn (Analyzer $analyzer) => $analyzer->charFilters())
            ->flatten();
    }

    public function defaultAnalyzer(): DefaultAnalyzer
    {
        return $this->defaultAnalyzer;
    }

    //TODO hmm don't like the naming
    public function setDefaultAnalyzer(Analyzer $analyzer): self
    {
        $this->defaultAnalyzer = $analyzer;

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

    public static function fromRaw(array $data): static
    {
        $defaultAnalyzerName = 'default';
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

            $analyzer[$name] = match ($name) {
                'default' => new DefaultAnalyzer($tokenizer, $analyzerFilters, $charFilters),
                default => new Analyzer($name, $tokenizer, $analyzerFilters, $charFilters)
            };
        }

        $defaultAnalyzer = $analyzers[$defaultAnalyzerName];

        $analysis = new Analysis($defaultAnalyzer, $analyzers,);

        return $analysis;
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

        $analyzers = $$this->analyzers
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
