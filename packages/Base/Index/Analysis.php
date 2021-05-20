<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Collection;

class Analysis
{
    protected Analyzer $analyzer;

    protected Tokenizer $tokenizer;

    protected string $analyzerName = 'default';

    public function __construct(
        protected array $filters = []
    ) {
        $this->tokenizer = new WordBoundaries();
        $this->analyzer = new Analyzer($this->analyzerName, $this->tokenizer, []);
    }

    public function createAnalyzer(string $name, Tokenizer $tokenizer,): Analyzer
    {
        $this->analyzerName = $name;
        $this->tokenizer = $tokenizer;
        $this->analyzer = new Analyzer($name, $this->tokenizer, [
            ...$this->filters
        ]);

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

        $result = [
            'analyzer' => $this->analyzer->raw(),
            'filter' => $filter,
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
