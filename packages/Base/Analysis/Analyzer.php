<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\Tokenizers\NonLetter;
use Sigmie\Base\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\Analyzer as AnalyzerInterface;
use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\Priority;
use Sigmie\Base\Contracts\Raw;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Base\Shared\Name;
use function Sigmie\Helpers\ensure_collection;
use function Sigmie\Helpers\name_configs;

use Sigmie\Support\Collection;

use Sigmie\Support\Contracts\Collection as CollectionInterface;

class Analyzer implements AnalyzerInterface
{
    use Name;

    protected CollectionInterface $filters;

    protected CollectionInterface $charFilters;

    protected Tokenizer $tokenizer;

    public function __construct(
        protected string $name,
        null|Tokenizer $tokenizer = null,
        array|CollectionInterface $filters = [],
        array|CollectionInterface $charFilters = [],
    ) {
        // 'standard' is the default Elasticsearch
        // tokenizer when no other is specified
        $this->tokenizer = $tokenizer ?: new WordBoundaries();
        $this->filters = ensure_collection($filters);
        $this->charFilters = ensure_collection($charFilters);
    }

    public static function create(
        array $raw,
        array $charFilters,
        array $filters,
        array $tokenizers
    ): static {

        $analyzerFilters = [];
        $analyzerCharFilters = [];

        [$name, $config] = name_configs($raw);

        foreach ($config['filter'] as $filterName) {
            $analyzerFilters[$filterName] = $filters[$filterName];
        }

        foreach ($config['char_filter'] as $filterName) {
            $analyzerCharFilters[$filterName] = match ($filterName) {
                'html_strip' => new HTMLStrip,
                default => $charFilters[$filterName]
            };
        }

        $tokenizerName = $config['tokenizer'];

        $analyzerTokenizer = match ($tokenizerName) {
            'whitespace' => new Whitespace,
            'letter' => new NonLetter,
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

    public function updateTokenizer(Tokenizer $tokenizer): void
    {
        $this->tokenizer = $tokenizer;
    }

    public function addFilters(CollectionInterface|array $filters): void
    {
        $filters = ensure_collection($filters);

        $this->filters = new Collection(array_merge(
            $this->filters->toArray(),
            $filters->toArray(),
        ));
    }

    public function addCharFilters(CollectionInterface|array $charFilters): void
    {
        $charFilters = ensure_collection($charFilters);

        $this->charFilters = new Collection(array_merge(
            $this->charFilters->toArray(),
            $charFilters->toArray(),
        ));
    }

    public function toRaw(): array
    {
        $filters = $this->sortedFilters();
        $charFilters = $this->charFilters();

        $result = [
            'tokenizer' => $this->tokenizer()->type(),
            'char_filter' => $charFilters->map(fn (CharFilter $filter) => $filter->name())->flatten()->toArray(),
            'filter' => $filters->map(fn (TokenFilter $filter) => $filter->name())->toArray()
        ];

        if ($this->tokenizer instanceof Configurable) {
            $result['tokenizer'] = $this->tokenizer->name();
        }

        return $result;
    }

    public function filters(): Collection
    {
        return $this->sortedFilters();
    }

    public function charFilters(): Collection
    {
        return $this->charFilters;
    }

    public function tokenizer(): Tokenizer
    {
        return $this->tokenizer;
    }

    protected function sortedCharFilters(): Collection
    {
        return $this->charFilters
            ->mapToDictionary(
                fn (CharFilter $filter) => [$filter->getPriority() => $filter]
            )
            ->sortByKeys();
    }

    protected function sortedFilters(): Collection
    {
        $filters = $this->filters->toArray();

        usort($filters, fn (Priority $a, Priority $b) => ($a->getPriority() < $b->getPriority()) ? -1 : 1);

        return (new Collection($filters))->mapToDictionary(fn (TokenFilter $tokenFilter) => [$tokenFilter->name() => $tokenFilter]);
    }
}
