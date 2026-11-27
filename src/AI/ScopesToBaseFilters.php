<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Sigmie\Analytics\Analytics;
use Sigmie\Document\AliveCollection;
use Sigmie\Parse\FilterParser;
use Sigmie\Search\NewSearch;

/**
 * Applies the server-controlled `$baseFilters` scope to every query a tool runs.
 *
 * The scope is parsed on its own and added as a separate filter clause. It is never joined with
 * the agent's filter text, so an agent filter with unbalanced parentheses cannot combine with it
 * into an OR that escapes the scope. A malformed scope throws a ParseException; it never falls
 * back to matching all documents. The using class must expose `protected SigmieIndex $index`
 * and `protected string $baseFilters`.
 */
trait ScopesToBaseFilters
{
    protected function applyBaseFilters(NewSearch|Analytics|AliveCollection $builder): void
    {
        if ($this->baseFilters === '') {
            return;
        }

        $builder->filterQuery((new FilterParser($this->index->properties()))->parse($this->baseFilters));
    }
}
