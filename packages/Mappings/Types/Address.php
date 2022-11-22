<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Exception;
use function Sigmie\Functions\name_configs;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Shared\Contracts\FromRaw;

class Address extends Text
{
    public function __construct(
        string $name,
    ) {
        parent::__construct($name, raw: 'keyword');

        $this->unstructuredText()->indexPrefixes();

        $this->withNewAnalyzer(function (NewAnalyzer $newAnalyzer) {
            $newAnalyzer->tokenizeOnPatternMatch('([0-9]+|[A-Za-z ]+ [0-9a-z]+)');
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
