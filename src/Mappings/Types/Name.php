<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\Analysis\TokenFilter\Ngram;
use Sigmie\Index\Analysis\TokenFilter\Synonyms;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchBoolPrefix;
use Sigmie\Query\Queries\Text\MatchPhrase;
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
            ->tokenFilter(new Ngram("{$this->name}_ngram", $this->minGrams, $this->maxGrams))
            // ->truncate($this->maxGramms)
            ->lowercase();

        $this->makeSortable();
    }

    public function names(): array
    {
        return [
            $this->name,
            "{$this->name}.{$this->name}_text",
        ];
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Match_($this->name, $queryString);
        $queries[] = new MatchPhrasePrefix("{$this->name}.{$this->name}_text", $queryString);
        $queries[] = new MatchBoolPrefix("{$this->name}.{$this->name}_text", $queryString);

        return $queries;
    }

    public function notAllowedFilters()
    {
        return [
            Synonyms::class
        ];
    }
}
