<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis;

use Sigmie\Index\Analysis\CharFilter\HTMLStrip;
use Sigmie\Index\Analysis\TokenFilter\AsciiFolding;
use Sigmie\Index\Analysis\TokenFilter\DecimalDigit;
use Sigmie\Index\Analysis\TokenFilter\Trim;
use Sigmie\Index\Analysis\TokenFilter\Unique;
use Sigmie\Index\Analysis\TokenFilter\Uppercase;
use Sigmie\Index\Analysis\Tokenizers\Generic as GenericTokenizer;
use Sigmie\Index\Analysis\Tokenizers\NonLetter;
use Sigmie\Index\Analysis\Tokenizers\Noop;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\CustomAnalyzer as CustomAnalyzerInterface;
use Sigmie\Index\Contracts\Tokenizer;
use Sigmie\Languages\English\Filter\Lowercase;
use Sigmie\Languages\English\Filter\Stemmer;
use Sigmie\Languages\English\Filter\Stopwords;
use Sigmie\Languages\German\Filter\LightStemmer as GermanLightStemmer;
use Sigmie\Languages\German\Filter\Lowercase as GermanLowercase;
use Sigmie\Languages\German\Filter\Normalize as GermanNormalize;
use Sigmie\Languages\German\Filter\Stopwords as GermanStopwords;
use Sigmie\Languages\Greek\Filter\Lowercase as GreekLowercase;
use Sigmie\Languages\Greek\Filter\Stemmer as GreekStemmer;
use Sigmie\Languages\Greek\Filter\Stopwords as GreekStopwords;
use Sigmie\Shared\Name;

use function Sigmie\Functions\analysis_list;
use function Sigmie\Functions\name_configs;

class Analyzer implements CustomAnalyzerInterface
{
    use Name;

    protected array $storedOptions = [];

    protected FilterChain $filters;

    protected FilterChain $charFilters;

    public function __construct(
        public readonly string $name,
        protected Tokenizer $tokenizer = new WordBoundaries,
        array $filters = [],
        array $charFilters = [],
        protected int|string|null $positionIncrementGap = null,
        protected int|string|null $offsetGap = null,
    ) {
        $this->filters = new FilterChain($filters);
        $this->charFilters = new FilterChain($charFilters);
    }

    public function __clone(): void
    {
        $this->tokenizer = clone $this->tokenizer;
        $this->filters = clone $this->filters;
        $this->charFilters = clone $this->charFilters;
    }

    public static function create(
        array $raw,
        array $charFilters,
        array $filters,
        array $tokenizers
    ): CustomAnalyzerInterface {
        $analyzerFilters = [];
        $analyzerCharFilters = [];

        [$name, $config] = name_configs($raw);

        foreach (analysis_list($config['filter'] ?? []) as $filterName) {
            $analyzerFilters[] = $filters[$filterName] ?? match ($filterName) {

                'autocomplete_english_stemmer' => new Stemmer($filterName),
                'autocomplete_english_stopwords' => new Stopwords($filterName),
                'autocomplete_english_lowercase' => new Lowercase($filterName),

                'autocomplete_german_normalization' => new GermanNormalize($filterName),
                'autocomplete_german_light_stemmer' => new GermanLightStemmer($filterName),
                'autocomplete_german_stopwords' => new GermanStopwords($filterName),
                'autocomplete_german_lowercase' => new GermanLowercase($filterName),

                'autocomplete_greek_stemmer' => new GreekStemmer($filterName),
                'autocomplete_greek_stopwords' => new GreekStopwords($filterName),
                'autocomplete_greek_lowercase' => new GreekLowercase($filterName),

                'lowercase' => new Lowercase($filterName),
                'ascii_folding' => new AsciiFolding($filterName),
                'asciifolding' => new AsciiFolding($filterName),
                'uppercase' => new Uppercase($filterName),
                'unique' => new Unique($filterName),
                'trim' => new Trim($filterName),
                'decimal_digit' => new DecimalDigit($filterName),
                default => new FilterReference($filterName),
            };
        }

        foreach (analysis_list($config['char_filter'] ?? []) as $filterName) {
            $analyzerCharFilters[] = match ($filterName) {
                'html_strip' => $charFilters[$filterName] ?? new HTMLStrip($filterName),
                default => $charFilters[$filterName] ?? new FilterReference($filterName),
            };
        }

        $tokenizerName = $config['tokenizer'] ?? null;

        $analyzerTokenizer = match ($tokenizerName) {
            'whitespace' => $tokenizers[$tokenizerName] ?? new Whitespace,
            'standard' => $tokenizers[$tokenizerName] ?? new WordBoundaries,
            'keyword' => $tokenizers[$tokenizerName] ?? new Noop,
            'letter' => $tokenizers[$tokenizerName] ?? new NonLetter('letter'),
            default => $tokenizers[$tokenizerName] ?? new GenericTokenizer($tokenizerName, ['type' => $tokenizerName]),
        };

        $gap = $config['position_increment_gap'] ?? null;
        $offsetGap = $config['offset_gap'] ?? null;

        $analyzer = match ($name) {
            'default' => new DefaultAnalyzer($analyzerTokenizer, $analyzerFilters, $analyzerCharFilters, $gap, $offsetGap),
            default => new Analyzer($name, $analyzerTokenizer, $analyzerFilters, $analyzerCharFilters, $gap, $offsetGap)
        };
        $analyzer->storedOptions = array_diff_key($config, array_flip(['tokenizer', 'filter', 'char_filter', 'position_increment_gap', 'offset_gap']));

        return $analyzer;
    }

    public function removeFilter(string $name): void
    {
        $this->filters = new FilterChain(array_filter(
            $this->filters->toArray(),
            fn ($filter): bool => $filter->name() !== $name,
        ));
    }

    public function removeCharFilter(string $name): void
    {
        $this->charFilters = new FilterChain(array_filter(
            $this->charFilters->toArray(),
            fn ($filter): bool => $filter->name() !== $name,
        ));
    }

    public function setTokenizer(Tokenizer $tokenizer): void
    {
        $this->tokenizer = $tokenizer;
    }

    public function addFilters(array $filters): void
    {
        $this->filters->addStages($filters);
    }

    public function addCharFilters(array $charFilters): void
    {
        $this->charFilters->addStages($charFilters);
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                ...$this->storedOptions,
                'tokenizer' => $this->tokenizer()->name(),
                'char_filter' => $this->charFilters->names(),
                'filter' => $this->filters->names(),
                ...($this->offsetGap === null ? [] : ['offset_gap' => $this->offsetGap]),
                ...($this->positionIncrementGap === null ? [] : ['position_increment_gap' => $this->positionIncrementGap]),
            ],
        ];
    }

    public function filters(): array
    {
        return $this->filters->toArray();
    }

    public function charFilters(): array
    {
        return $this->charFilters->toArray();
    }

    public function tokenizer(): Tokenizer
    {
        return $this->tokenizer;
    }
}
