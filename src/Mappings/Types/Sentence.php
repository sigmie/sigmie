<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Text\Match_;

class Sentence extends Text implements Analyze
{
    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer
            ->tokenizeOnWordBoundaries()
            ->lowercase();

        $this->makeSortable();
    }

    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes()->keyword();
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Prefix($this->name, $queryString);
        $queries[] = new Match_($this->name, $queryString);

        return $queries;
    }
}
