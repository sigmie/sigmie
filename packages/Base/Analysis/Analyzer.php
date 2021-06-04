<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\Priority;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Collection;

class Analyzer
{
    protected string $name = 'analyzer';

    protected array $sortedFilters = [];

    public function __construct(
        string $prefix,
        protected Tokenizer $tokenizer,
        array $filters = [],
        protected array $charFilters = [],
    ) {
        $this->name = "{$prefix}_{$this->name}";

        $this->sortedFilters = $this->sortedFilters($filters);
    }

    private function sortedFilters(array $filters)
    {
        $res = [];
        foreach ($filters as $filter) {
            $res[$filter->getPriority()] = $filter;
        }

        ksort($res);

        return array_values($res);
    }

    public function raw(): array
    {
        $filters = new Collection($this->sortedFilters);
        $charFilters = new Collection($this->charFilters);

        $result = [
            'tokenizer' => $this->tokenizer()->type(),
            'char_filter' => $charFilters->map(fn (CharFilter $filter) => $filter->name())->toArray(),
            'filter' => $filters->map(fn (TokenFilter $filter) => $filter->name())->toArray()
        ];

        if ($this->tokenizer instanceof Configurable) {
            $result['tokenizer'] = $this->tokenizer->name();
        }

        return $result;
    }

    public static function fromRaw(array $data): Analyzer
    {
        return new Analyzer('demo', new WordBoundaries(100), []);
    }

    public function setFilters(array $filters)
    {
        $this->sortedFilters = $this->sortedFilters($filters);
    }

    public function filters(): array
    {
        return $this->sortedFilters;
    }

    public function tokenizer(): Tokenizer
    {
        return $this->tokenizer;
    }

    public function name()
    {
        return $this->name;
    }
}
