<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;

class Email extends Text implements Analyze
{
    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes()->keyword();
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer->tokenizeOnPattern('(@|\.)');
        $newAnalyzer->lowercase();

        $this->makeSortable();
    }

    public function queries(array|string $queryString): array
    {
        return [new Match_($this->name, $queryString, analyzer: $this->searchAnalyzer()), new Prefix($this->name, $queryString), new Term($this->name.'.keyword', $queryString)];
    }
}
