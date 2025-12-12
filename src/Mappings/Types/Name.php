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

use function Sigmie\Functions\random_name;

class Name extends Text implements Analyze
{
    protected string $prefixAnalyzer = 'name_text_field_analyzer';

    protected string $ngramAnalyzer = 'name_ngram_field_analyzer';

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
        $prefixField = (new Text($this->name.'_text'))->unstructuredText()
            ->withNewAnalyzer(function (NewAnalyzer $newAnalyzer): void {
                $newAnalyzer->tokenizeOnWhitespaces()
                    ->name($this->prefixAnalyzer)
                    ->lowercase()
                    ->truncate($this->minGrams - 1)
                    ->trim();
            });

        $this->field($prefixField);

        $newAnalyzer
            ->tokenizeOnWhitespaces()
            ->name($this->ngramAnalyzer)
            ->tokenFilter(new Ngram(random_name('ngram'), $this->minGrams, $this->maxGrams))
            // ->truncate($this->maxGramms)
            ->lowercase();

        $this->makeSortable();
    }

    public function names(): array
    {
        return [
            $this->name,
            $this->fullPath() . '.' . $this->name . '_text',
        ];
    }

    public function queries(array|string $queryString): array
    {
        $queries = [];

        $prefixField = $this->fullPath() . '.' . $this->name . '_text';

        $queries[] = new Match_($this->name(), $queryString, analyzer: $this->ngramAnalyzer);
        $queries[] = new MatchPhrasePrefix($prefixField, $queryString, analyzer: $this->prefixAnalyzer);
        $queries[] = new MatchBoolPrefix($prefixField, $queryString, analyzer: $this->prefixAnalyzer);

        return $queries;
    }

    public function notAllowedFilters(): array
    {
        return [
            Synonyms::class,
        ];
    }
}
