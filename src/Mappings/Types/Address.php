<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Text\MatchBoolPrefix;

class Address extends Text implements Analyze
{
    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes()->keyword();
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer->tokenizeOnWordBoundaries();
        $newAnalyzer->lowercase();

        $this->makeSortable();
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new MatchBoolPrefix($this->name, $queryString);

        return $queries;
    }
}
