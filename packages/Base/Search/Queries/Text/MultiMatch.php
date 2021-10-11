<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Text;

use Sigmie\Base\Search\Queries\QueryClause;

class MultiMatch extends QueryClause
{
    public function __construct(
        protected array $fields,
        protected string $query,
    ) {
    }
    public function toRaw(): array
    {
        return [
            'multi_match' => [
                'query' => $this->query,
                'fields' => $this->fields
            ]
        ];
    }
}
