<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Contract\CharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Collection;

class Analyzer
{
    public function __construct(
        protected string $name,
        protected Tokenizer $tokenizer,
        protected array $filters,
        protected array $charFilterNames = [],
    ) {
    }

    public function raw(): array
    {
        $filters = new Collection($this->filters);

        $result = [
            $this->name() => [
                'tokenizer' => $this->tokenizer()->type(),
                'char_filter' => $this->charFilterNames,
                'filter' => $filters->map(fn (TokenFilter $filter) => $filter->name())->toArray()
            ],
        ];

        if ($this->tokenizer instanceof Configurable) {
            $result[$this->name()]['tokenizer'] = $this->tokenizer->name();
        }

        return $result;
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
