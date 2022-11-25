<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Query\Queries\Text\Match_;

class HTML extends Text
{
    public function __construct(
        string $name,
    ) {
        parent::__construct($name, raw: null);

        $this->unstructuredText();

        $this->withNewAnalyzer(function (NewAnalyzer $newAnalyzer) {
            $newAnalyzer->tokenizeOnWordBoundaries();
            $newAnalyzer->stripHTML();
            $newAnalyzer->trim();
            $newAnalyzer->lowercase();
            $newAnalyzer->unique();
        });
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Match_($this->name, $queryString);

        return $queries;
    }
}
