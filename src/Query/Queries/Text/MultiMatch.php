<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Queries\Query;

class MultiMatch extends Query
{
    public function __construct(
        protected array $fields,
        protected string $query,
        protected string $analyzer = 'default',
    ) {}

    public function toRaw(): array
    {
        $res = [
            'multi_match' => [
                'query' => $this->query,
                'boost' => $this->boost,
                'analyzer' => $this->analyzer,
            ],
        ];

        if ($this->fields !== []) {
            $res['multi_match']['fields'] = $this->fields;
        }

        return $res;
    }
}
