<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use PhpParser\Node\Expr\Instanceof_;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contract\CharFilter;
use Sigmie\Base\Contracts\CharFilter as ContractsCharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Cli\Config;
use Sigmie\Support\Collection;

class Analysis
{
    protected Analyzer $analyzer;

    protected Tokenizer $tokenizer;

    protected string $analyzerName = 'default';

    public function __construct(
        protected array $filters = [],
        protected array $charFilters = []
    ) {
        $this->tokenizer = new WordBoundaries();
        $this->analyzer = new Analyzer($this->analyzerName, $this->tokenizer, []);
    }

    public function createAnalyzer(string $name, Tokenizer $tokenizer,): Analyzer
    {
        $charFilters = new Collection($this->charFilters);

        $charFilterNames = $charFilters->map(function (ContractsCharFilter $filter) {
            return $filter->name();
        })->toArray();

        $this->analyzerName = $name;
        $this->tokenizer = $tokenizer;
        $this->analyzer = new Analyzer($name, $this->tokenizer, [
            ...$this->filters
        ], $charFilterNames);

        return $this->analyzer;
    }

    public function addLanguageFilters(Language $language)
    {
        $this->filters = [
            ...$this->filters,
            $language->stopwords(),
            ...$language->stemmers()
        ];

        return $this;
    }

    public static function fromRaw(array $data): Analysis
    {
        $rawFilters = $data['filter'];
        $rawChar = $data['char_filter'];
        $filters = [];
        $charFilters = [];

        foreach ($rawFilters as $filter) {
            $type = $filter['type'];

        }

        return new Analysis();
    }

    public function raw(): array
    {
        $filter = new Collection($this->filters);

        $filter = $filter->mapToDictionary(function (TokenFilter $filter) {

            $value = $filter->value();
            $value['type'] = $filter->type();

            return [
                $filter->name() => $value
            ];
        })->toArray();

        $charFilters = new Collection($this->charFilters);

        $charFilters = $charFilters
            ->filter(fn ($filter) => $filter instanceof Configurable)
            ->mapToDictionary(function (Configurable $filter) {
                return [$filter->name() => $filter->config()];
            })->toArray();

        $result = [
            'analyzer' => $this->analyzer->raw(),
            'filter' => $filter,
            'char_filter' => $charFilters,
            'default' => [
                'type' => $this->analyzerName
            ]
        ];

        if ($this->tokenizer instanceof Configurable) {
            $result['tokenizer'] = $this->tokenizer->config();
        }

        return $result;
    }
}
