<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchBoolPrefix;

class Address extends Text implements Analyze
{
    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes()->keyword();

        $this->makeSortable();
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer->tokenizeOnWordBoundaries();
        $newAnalyzer->lowercase();
    }

    public function queries(array|string $queryString): array
    {
        // Elasticsearch never applies fuzziness to the last term of a bool_prefix query, so a typo
        // in the last word would match only through the plain match.
        return $this->queryStrings($queryString, fn (string $queryString): array => [
            new MatchBoolPrefix($this->name(), $queryString, analyzer: $this->searchAnalyzer()),
            new Match_($this->name(), $queryString, analyzer: $this->searchAnalyzer()),
        ]);
    }
}
