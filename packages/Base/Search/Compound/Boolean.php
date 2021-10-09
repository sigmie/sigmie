<?php

namespace Sigmie\Base\Search\Compound;

use Sigmie\Base\Search\Compound\Boolean\QueryBuilder as BooleanQueryBuilder;
use Sigmie\Base\Search\Operators\Must;
use Sigmie\Base\Search\QueryBuilder;

/**
 * @property-read BooleanQueryBuilder $mustNot
 * @property-read BooleanQueryBuilder $should
 * @property-read BooleanQueryBuilder $filter
 * @property-read BooleanQueryBuilder $must
 */
class Boolean
{
    protected array $query = [
        'must' => [],
        'must_not' => [],
        'should' => [],
        'filter' => [],
    ];

    public function must()
    {
        $builder = new BooleanQueryBuilder();

        $this->query['must'][] = $builder;

        return $builder;
    }

    public function mustNot()
    {
        return new QueryBuilder();
    }

    public function should()
    {
        return new QueryBuilder();
    }

    public function filter()
    {
        return new QueryBuilder();
    }

    public function toRaw()
    {
        $res = [];
        foreach ($this->query as $operator => $queries) {
            $res[$operator] = [];
            foreach ($queries as $query) {
                $res[$operator] = $query->toRaw();
            }
        }

        return ['bool' => $res];
    }
}
