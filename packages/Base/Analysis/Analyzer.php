<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\Analyzer as AnalyzerInterface;
use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\Priority;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Base\Name;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

use function Sigmie\Helpers\ensure_collection;

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

        return new Collection($filters);
    }

    public function toRaw(): array
    {
        $filters = $this->sortedFilters();
        $charFilters = $this->charFilters;

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
}
