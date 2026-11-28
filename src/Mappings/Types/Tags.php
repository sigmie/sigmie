<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchBoolPrefix;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;

class Tags extends Text implements Analyze
{
    public function configure(): void
    {
        // docs/facets.md counts each tag value on the keyword sub-field, not on analyzed tokens.
        $this->unstructuredText()->keyword();
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer->mapChars(['#' => ''])
            ->tokenizeOnWordBoundaries()
            ->lowercase();
    }

    public function queries(array|string $queryString): array
    {
        return $this->queryStrings($queryString, fn (string $queryString): array => [
            new Match_($this->name(), $queryString, analyzer: $this->searchAnalyzer()),
            new MatchPhrasePrefix($this->name(), $queryString, analyzer: $this->searchAnalyzer()),
            new MatchBoolPrefix($this->name(), $queryString, analyzer: $this->searchAnalyzer()),
        ]);
    }
}
