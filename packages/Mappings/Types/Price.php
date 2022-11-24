<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Exception;
use function Sigmie\Functions\name_configs;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Shared\Contracts\FromRaw;

class Price extends Type
{
    public function toRaw(): array
    {
        return [$this->name => [
            'type' => 'scaled_float',
            'scaling_factor' => 100
        ]];
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        // It's unlikely to search in an input field
        // for a price.

        // Price type is better for range filters

        return $queries;
    }
}
