<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;

class Tags extends Text implements Analyze
{
    public function configure(): void
    {
        $this->unstructuredText();
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer->mapChars(['#' => ''])
            ->tokenizeOnWordBoundaries()
            ->lowercase();
    }

    public function queries(array|string $queryString): array
    {
        $queries = [];

        $queries[] = new Match_($this->name, $queryString, analyzer: $this->searchAnalyzer());
        $queries[] = new MatchPhrasePrefix($this->name, $queryString, analyzer: $this->searchAnalyzer());

        return $queries;
    }
}
