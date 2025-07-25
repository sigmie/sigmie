<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis;

use Sigmie\Languages\English\Filter\Lowercase;
use Sigmie\Languages\English\Filter\Stemmer;
use Sigmie\Languages\English\Filter\Stopwords;
use Sigmie\Languages\German\Filter\GermanNormalization;
use Sigmie\Languages\German\Filter\LightStemmer as GermanLightStemmer;
use Sigmie\Languages\German\Filter\Lowercase as GermanLowercase;
use Sigmie\Languages\German\Filter\Stopwords as GermanStopwords;
use Sigmie\Languages\Greek\Filter\Lowercase as GreekLowercase;
use Sigmie\Languages\Greek\Filter\Stemmer as GreekStemmer;
use Sigmie\Languages\Greek\Filter\Stopwords as GreekStopwords;
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;
use Sigmie\Index\Analysis\TokenFilter\AsciiFolding;
use Sigmie\Index\Analysis\TokenFilter\DecimalDigit;
use Sigmie\Index\Analysis\TokenFilter\Shingle;
use Sigmie\Index\Analysis\TokenFilter\Trim;
use Sigmie\Index\Analysis\TokenFilter\Unique;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\CharFilter;
use Sigmie\Index\Contracts\CustomAnalyzer as CustomAnalyzerInterface;
use Sigmie\Index\Contracts\TokenFilter;
use Sigmie\Index\Contracts\Tokenizer;
use Sigmie\Shared\Collection;
use Sigmie\Shared\Name;

use function Sigmie\Functions\name_configs;

class Analyzer implements CustomAnalyzerInterface
{
    use Name;

    protected Collection $filters;

    protected Collection $charFilters;

    protected Tokenizer $tokenizer;

    public function __construct(
        public readonly string $name,
        Tokenizer $tokenizer = new WordBoundaries(),
        array $filters = [],
        array $charFilters = [],
    ) {
        // 'standard' is the default Elasticsearch
        // tokenizer when no other is specified
        $this->tokenizer = $tokenizer;
        $this->filters = new Collection($filters);
        $this->charFilters = new Collection($charFilters);
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

        foreach ($config['filter'] ?? [] as $filterName) {
            $analyzerFilters[$filterName] = match ($filterName) {

                'autocomplete_english_stemmer' => new Stemmer($filterName),
                'autocomplete_english_stopwords' => new Stopwords($filterName),
                'autocomplete_english_lowercase' => new Lowercase($filterName),

                'autocomplete_german_normalization' => new GermanNormalization(),
                'autocomplete_german_light_stemmer' => new GermanLightStemmer($filterName),
                'autocomplete_german_stopwords' => new GermanStopwords($filterName),
                'autocomplete_german_lowercase' => new GermanLowercase($filterName),

                'autocomplete_greek_stemmer' => new GreekStemmer($filterName),
                'autocomplete_greek_stopwords' => new GreekStopwords($filterName),
                'autocomplete_greek_lowercase' => new GreekLowercase($filterName),

                'lowercase' => new Lowercase($filterName),
                'ascii_folding' => new AsciiFolding($filterName),
                'unique' => new Unique($filterName),
                'trim' => new Trim($filterName),
                'decimal_digit' => new DecimalDigit($filterName),
                'shingle' => new Shingle($filterName),

                default => $filters[$filterName]
            };
        }

        foreach ($config['char_filter'] ?? [] as $filterName) {
            $analyzerCharFilters[$filterName] = match ($filterName) {
                'html_strip' => new HTMLStrip(),
                default => $charFilters[$filterName]
            };
        }

        $tokenizerName = $config['tokenizer'] ?? null;

        $analyzerTokenizer = match ($tokenizerName) {
            'whitespace' => new Whitespace(),
            'standard' => new WordBoundaries(),
            default => $tokenizers[$tokenizerName]
        };

        return match ($name) {
            'default' => new DefaultAnalyzer($analyzerTokenizer, $analyzerFilters, $analyzerCharFilters),
            default => new Analyzer($name, $analyzerTokenizer, $analyzerFilters, $analyzerCharFilters)
        };
    }

    public function removeFilter(string $name): void
    {
        $this->filters->remove($name);
    }

    public function removeCharFilter(string $name): void
    {
        $this->charFilters->remove($name);
    }

    public function setTokenizer(Tokenizer $tokenizer): void
    {
        $this->tokenizer = $tokenizer;
    }

    public function addFilters(array $filters): void
    {
        $this->filters = $this->filters->merge($filters);
    }

    public function addCharFilters(array $charFilters): void
    {
        $this->charFilters = $this->charFilters->merge($charFilters);
    }

    public function toRaw(): array
    {
        $filters = $this->filters
            ->map(fn (TokenFilter $filter) => $filter->name())
            ->flatten()
            ->toArray();

        $charFilters = $this->charFilters
            ->map(fn (CharFilter $charFilter) => $charFilter->name())
            ->flatten()
            ->toArray();

        $raw = [
            $this->name => [
                'tokenizer' => $this->tokenizer()->name(),
                'char_filter' => $charFilters,
                'filter' => $filters,
            ],
        ];

        return $raw;
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
