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

class SearchableBoolean extends Boolean
{
    public function __construct(protected string $true, protected string $false)
    {
        parent::__construct($this->true);
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        if (str_contains(trim(strtolower($queryString)), $this->true)) {
            $queries[] = new Term($this->name, true);
        }

        if (str_contains(trim(strtolower($queryString)), $this->false)) {
            $queries[] = new Term($this->name, false);
        }

        return $queries;
    }
}
