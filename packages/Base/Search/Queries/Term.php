<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries;

use Sigmie\Base\Search\QueryBuilder;
use Sigmie\Base\Search\SearchBuilder;

class Term
{
    public function __construct(public QueryBuilder $searchBuilder)
    {
    }

    public function term($field, $value)
    {
        $this->field = $field;
        $this->value = $value;

        return $this;
    }

    public function toRaw()
    {
        return [
            'term' => [
                $this->field => [
                    'value' => $this->value
                ]
            ]
        ];
    }
}
