<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Exception;
use function Sigmie\Functions\name_configs;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Shared\Contracts\FromRaw;

class LongText extends Text
{
    public function __construct(
        string $name,
    ) {
        parent::__construct($name, raw: null);

        $this->unstructuredText();
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Term($this->name, $queryString);

        return $queries;
    }
}
