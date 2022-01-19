<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries;

class MatchNone extends Query
{
    public function toRaw(): array
    {
        return [
            'match_none' => (object) [],
        ];
    }
}
