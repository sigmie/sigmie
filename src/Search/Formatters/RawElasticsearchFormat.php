<?php

declare(strict_types=1);

namespace Sigmie\Search\Formatters;

use Sigmie\Document\Hit;

use function Sigmie\Functions\search_hits;

class RawElasticsearchFormat extends AbstractFormatter
{
    public function hits(): array
    {
        return array_map(Hit::fromRaw(...), search_hits($this->queryResponseRaw));
    }

    public function format(): array
    {
        return $this->queryResponseRaw;
    }
}
