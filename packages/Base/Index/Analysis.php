<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\CharFilter as ContractsCharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\RawRepresentation;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Collection;

class Analysis implements RawRepresentation
{
    protected Analyzer $defaultAnalyzer;

    public function __construct(
        protected Tokenizer $tokenizer,
        protected array $filters = [],
        protected array $charFilters = [],
        ?Analyzer $defaultAnalyzer = null

    ) {
        $this->defaultAnalyzer = $defaultAnalyzer ?: new Analyzer('default', $this->tokenizer, []);
    }

    public function tokenizer(): Tokenizer
    {
        return $this->tokenizer;
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

    public static function fromRaw(array $data): static
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

        $analyzerInstance = new Analyzer('demo', new WordBoundaries(100), []);

        foreach ($data['analyzer'] as $name => $analyzer) {
            $analyzerFilters = [];
            foreach ($analyzer['filter'] as $filterName) {
                $analyzerFilters[] = $filters[$filterName];
            }

            $analyzerCharFilters = [];
            foreach ($analyzer['char_filter'] as $filterName) {
                $analyzerCharFilters[] = $charFilters[$filterName];
            }

            $tokenizer = $tokenizers[$analyzer['tokenizer']];
            //TODO tokenizer
            // $defaultAnalyzerName = $data['default']['type'];
            $analyzerInstance = new Analyzer($name, $tokenizer, $analyzerFilters, $charFilters);
            break; // Use only the first analyzer for now
        }

        // analyzerName 
        // $analyzer = Analyzer::fromRaw();
        $analysis = new Analysis($tokenizer, array_values($filters), array_values($charFilters));
        $analysis->addAnalyzer($analyzerInstance);

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


        $result = [
            'analyzer' => $this->defaultAnalyzer->raw(),
            'filter' => $filter,
            'char_filter' => $charFilters,
        ];

        if ($this->tokenizer instanceof Configurable) {
            $result['tokenizer'] = $this->tokenizer->config();
        }

        return $result;
    }
}
