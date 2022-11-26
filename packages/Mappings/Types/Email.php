<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Exception;
use function Sigmie\Functions\name_configs;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Mappings\Contracts\Configure;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Shared\Contracts\FromRaw;

class Email extends Text implements Configure, Analyze
{
    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes()->keyword();
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer->tokenizeOnPattern('(@|\.)');
        $newAnalyzer->lowercase();
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
