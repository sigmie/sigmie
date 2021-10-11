<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries;

class MatchNone extends QueryClause
{
    public function toRaw(): array
    {
        return [
            'match_none' => (object) []
        ];
    }
}
