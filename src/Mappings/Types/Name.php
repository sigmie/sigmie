<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\Analysis\TokenFilter\Ngram;
use Sigmie\Index\Analysis\TokenFilter\Synonyms;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchBoolPrefix;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;

class Name extends Text implements Analyze
{
    public function __construct(
        string $name,
        protected int $minGrams = 4,
        protected int $maxGrams = 5
    ) {
        parent::__construct($name);
    }

    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes()->keyword();
    }

    public function semantic(bool $semantic = true)
    {
        // Names are not semantic
        $this->semantic = false;
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $prefixField = (new Text("{$this->name}_text"))->unstructuredText()->withNewAnalyzer(function (NewAnalyzer $newAnalyzer) {
            $newAnalyzer->tokenizeOnWhitespaces()
                ->lowercase()
                ->truncate($this->minGrams - 1)
                ->trim();
        });

        $this->field($prefixField);

        $newAnalyzer
            ->tokenizeOnWhitespaces()
            ->tokenFilter(new Ngram(prefix_id('ngram'), $this->minGrams, $this->maxGrams))
            // ->truncate($this->maxGramms)
            ->lowercase();

        $this->makeSortable();
    }

    public function names(): array
    {
        return [
            $this->name,
            // "{$this->name}.{$this->name}_text",
            $this->parentPath ? "{$this->parentPath}.{$this->name}.{$this->name}_text" : "{$this->name}.{$this->name}_text",
        ];
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $prefixField = $this->parentPath ? "{$this->parentPath}.{$this->name}.{$this->name}_text" : "{$this->name}.{$this->name}_text";

        $queries[] = new Match_($this->name, $queryString);
        $queries[] = new MatchPhrasePrefix($prefixField, $queryString);
        $queries[] = new MatchBoolPrefix($prefixField, $queryString);

        return $queries;
    }

    public function notAllowedFilters()
    {
        return [
            Synonyms::class,
        ];
    }
}
