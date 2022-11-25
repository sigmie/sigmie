<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;

class Email extends Text
{
    public function __construct(
        string $name,
    ) {
        parent::__construct($name, raw: 'keyword');

        $this->unstructuredText()->indexPrefixes();

        $this->withNewAnalyzer(function (NewAnalyzer $newAnalyzer) {
            $newAnalyzer->tokenizeOnPattern('(@|\.)');
            $newAnalyzer->lowercase();
        });
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Match_($this->name, $queryString);

        $queries[] = new Prefix($this->name, $queryString);

        $queries[] = new Term("{$this->name}.keyword", $queryString);

        return $queries;
    }
}
