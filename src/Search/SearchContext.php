<?php

declare(strict_types=1);

namespace Sigmie\Search;

class SearchContext
{
    public function __construct(
        public array $queryStrings = [],
        public array $queryImages = [],
        public string $filterString = '',
        public string $facetFilterString = '',
        public string $sortString = '',
        public string $facetString = '',
        public array $facetFields = [],
        public int $size = 20,
        public int $from = 0,
        public array $autocompletePrefixStrings = [],
    ) {}
}
