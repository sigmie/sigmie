<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Collection;

class Analyzer
{
    protected string $name = 'analyzer';

    public function __construct(
        string $prefix,
        protected Tokenizer $tokenizer,
        protected array $filters = [],
        protected array $charFilters = [],
    ) {

        $this->name = "{$prefix}_{$this->name}";
    }

    public function raw(): array
    {
        $filters = new Collection($this->filters);
        $charFilters = new Collection($this->charFilters);

        $result = [
            $this->name() => [
                'tokenizer' => $this->tokenizer()->type(),
                'char_filter' => $charFilters->map(fn (CharFilter $filter) => $filter->name())->toArray(),
                'filter' => $filters->map(fn (TokenFilter $filter) => $filter->name())->toArray()
            ],
        ];

        if ($this->tokenizer instanceof Configurable) {
            $result[$this->name()]['tokenizer'] = $this->tokenizer->name();
        }

        return $result;
    }

    public static function fromRaw(array $data): Analyzer
    {
        return new Analyzer('demo', new WordBoundaries(100), []);
    }

    public function filters(): array
    {
        return $this->filters;
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
