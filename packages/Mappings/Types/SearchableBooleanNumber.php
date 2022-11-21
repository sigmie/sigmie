<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Exception;
use function Sigmie\Functions\name_configs;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Shared\Contracts\FromRaw;

class SearchableBooleanNumber extends Number
{
    protected $callable;

    public function __construct(string $name, null|callable $callable = null)
    {
        parent::__construct($name);

        if (is_null($callable)) {
            $callable = fn ($queryString) => str_contains($queryString, $this->name);
        }

        $this->callable = $callable;

        $this->integer();
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        if (($this->callable)($queryString)) {
            $queries[] = new Range($this->name, ['>' => 0]);
        }

        return $queries;
    }
}
