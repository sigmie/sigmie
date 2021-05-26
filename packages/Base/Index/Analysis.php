<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use PhpParser\Node\Expr\Instanceof_;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contract\CharFilter;
use Sigmie\Base\Contracts\CharFilter as ContractsCharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Cli\Config;
use Sigmie\Support\Collection;

class Analysis
{
    protected Analyzer $analyzer;

    protected Tokenizer $tokenizer;

    protected string $analyzerName = 'default';

    public function __construct(
        protected array $filters = [],
        protected array $charFilters = []
    ) {
        $this->tokenizer = new WordBoundaries();
        $this->analyzer = new Analyzer($this->analyzerName, $this->tokenizer, []);
    }

    public function createAnalyzer(string $name, Tokenizer $tokenizer,): Analyzer
    {
        $charFilters = new Collection($this->charFilters);

        $charFilterNames = $charFilters->map(function (ContractsCharFilter $filter) {
            return $filter->name();
        })->toArray();

        $this->analyzerName = $name;
        $this->tokenizer = $tokenizer;
        $this->analyzer = new Analyzer($name, $this->tokenizer, [
            ...$this->filters
        ], $charFilterNames);

        return $this->analyzer;
    }

    public function setAnalyzer(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
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

    public static function fromRaw(array $data): Analysis
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
            $analyzerInstance = new Analyzer($name, $tokenizer, $analyzerFilters, $charFilters);
            break; // Use only the first analyzer for now
        }

        // $analyzer = Analyzer::fromRaw();
        $analysis = new Analysis(array_values($filters), array_values($charFilters));
        $analysis->setAnalyzer($analyzerInstance);

        return $analysis;
    }

    public function raw(): array
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
            'analyzer' => $this->analyzer->raw(),
            'filter' => $filter,
            'char_filter' => $charFilters,
            'default' => [
                'type' => $this->analyzerName
            ]
        ];

        if ($this->tokenizer instanceof Configurable) {
            $result['tokenizer'] = $this->tokenizer->config();
        }

        return $result;
    }
}
