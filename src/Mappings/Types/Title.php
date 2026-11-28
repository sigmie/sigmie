<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchBoolPrefix;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;

class Title extends Text implements Analyze
{
    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer
            ->tokenizeOnWordBoundaries()
            ->lowercase();
    }

    public function configure(): void
    {
        $this->unstructuredText()->keyword();

        $this->makeSortable();
    }

    public function queries(array|string $queryString): array
    {
        return $this->queryStrings($queryString, fn (string $queryString): array => [
            new MatchPhrasePrefix($this->name(), $queryString, analyzer: $this->searchAnalyzer()),
            new MatchBoolPrefix($this->name(), $queryString, analyzer: $this->searchAnalyzer()),
            new Match_($this->name(), $queryString, analyzer: $this->searchAnalyzer()),
        ]);
    }
}
