<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Compound;

use Sigmie\Base\Search\BooleanQueryBuilder;
use Sigmie\Base\Search\Queries\QueryClause;

class Boolean extends QueryClause
{
    public BooleanQueryBuilder $must;

    public BooleanQueryBuilder $mustNot;

    public BooleanQueryBuilder $should;

    public BooleanQueryBuilder $filter;

    public function __construct()
    {
        $this->must = new BooleanQueryBuilder;
        $this->mustNot = new BooleanQueryBuilder;
        $this->filter = new BooleanQueryBuilder;
        $this->should = new BooleanQueryBuilder;
    }

    public function must(): BooleanQueryBuilder
    {
        return $this->must;
    }

    public function mustNot(): BooleanQueryBuilder
    {
        return $this->mustNot;
    }

    public function should(): BooleanQueryBuilder
    {
        return $this->should;
    }

    public function filter(): BooleanQueryBuilder
    {
        return $this->filter;
    }

    public function toRaw(): array
    {
        $res = [];

        if (count($this->must->toRaw()) > 0) {
            $res['must'] = $this->must->toRaw();
        }

        if (count($this->mustNot->toRaw()) > 0) {
            $res['must_not'] = $this->mustNot->toRaw();
        }

        if (count($this->should->toRaw()) > 0) {
            $res['should'] = $this->should->toRaw();
        }

        if (count($this->filter->toRaw()) > 0) {
            $res['filter'] = $this->filter->toRaw();
        }

        return ['bool' => $res];
    }
}
