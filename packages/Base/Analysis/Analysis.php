<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Exception;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\CharFilter\HTMLFilter;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Analysis\TokenFilter\TokenFilter as TokenFilterTokenFilter;
use Sigmie\Base\Analysis\Tokenizers\NonLetter;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Contracts\Analysis as AnalysisInterface;
use Sigmie\Base\Contracts\Analyzers;
use Sigmie\Base\Contracts\CharFilter as ContractsCharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\Name;
use Sigmie\Base\Contracts\Raw;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
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
        ?DefaultAnalyzer $defaultAnalyzer = null,
        array|CollectionInterface $analyzers = [],
    ) {
        $this->analyzers = ensure_collection($analyzers);

        if (!is_null($defaultAnalyzer)) {
            $this->analyzers->set('default', $defaultAnalyzer);
        }

        $this->initProps();
    }

    public function tokenizers(): CollectionInterface
    {
        return $this->tokenizers;
    }

    public function updateAnalyzers(array|CollectionInterface $analyzers): void
    {
        // $this->analyzers = ensure_collection($analyzers);

        $oldAnalyzers = ensure_collection($analyzers);

        $oldAnalyzers = $this->tokenizers->toArray();
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

    public function setAnalyzer(Analyzer $analyzer)
    {
        $this->analyzers[$analyzer->name()] = $analyzer;
    }


    public function addLanguageFilters(Language $language): static
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

            $filters[$name] = TokenFilterTokenFilter::fromRaw([$name => $filter]);
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

        return new Analysis($defaultAnalyzer, $analyzers,);
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

        return [
            'analyzer' => $analyzers,
            'filter' => $filter,
            'char_filter' => $charFilters,
            'tokenizer' => $tokenizer
        ];
    }

    private function allAnalyzers(): CollectionInterface
    {
        return $this->analyzers;
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
}
