<?php

declare(strict_types=1);

namespace Sigmie\Search\Formatters;

class FormatterParameters
{
    private array $queryStrings = [];

    private string $filterString = '';

    private string $sortString = '';

    private string $facetString = '';

    private int $size = 20;

    private int $from = 0;

    private array $meta = [];

    public function queryStrings(array $queryStrings): self
    {
        $this->queryStrings = $queryStrings;
        return $this;
    }

    public function filters(string $filterString): self
    {
        $this->filterString = $filterString;
        return $this;
    }

    public function sort(string $sortString): self
    {
        $this->sortString = $sortString;
        return $this;
    }

    public function facets(string $facetString): self
    {
        $this->facetString = $facetString;
        return $this;
    }

    public function pagination(int $size, int $from): self
    {
        $this->size = $size;
        $this->from = $from;
        return $this;
    }

    public function meta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'queryStrings' => $this->queryStrings,
            'filterString' => $this->filterString,
            'sortString' => $this->sortString,
            'facetString' => $this->facetString,
            'size' => $this->size,
            'from' => $this->from,
            'meta' => $this->meta,
        ];
    }

    // Getters
    public function getQueryStrings(): array { return $this->queryStrings; }

    public function getFilterString(): string { return $this->filterString; }

    public function getSortString(): string { return $this->sortString; }

    public function getFacetString(): string { return $this->facetString; }

    public function getSize(): int { return $this->size; }

    public function getFrom(): int { return $this->from; }

    public function getMeta(): array { return $this->meta; }
} 
