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

class Active extends Boolean
{
    public function __construct()
    {
        parent::__construct('active');
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        if (str_contains(trim(strtolower($queryString)), 'active')) {
            $queries[] = new Term($this->name, true);
        }

        if (str_contains(trim(strtolower($queryString)), 'inactive')) {
            $queries[] = new Term($this->name, false);
        }

        return $queries;
    }
}
