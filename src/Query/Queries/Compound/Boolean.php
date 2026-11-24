<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Compound;

use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Query\BooleanQueryBuilder;
use Sigmie\Query\Queries\Query;

use function Sigmie\Functions\merge_named;

class Boolean extends Query
{
    public BooleanQueryBuilder $must;

    public BooleanQueryBuilder $mustNot;

    public BooleanQueryBuilder $should;

    public BooleanQueryBuilder $filter;

    public array $raw = [];

    public function __construct(NewProperties|Properties $properties = new NewProperties)
    {
        $this->must = new BooleanQueryBuilder($properties);
        $this->mustNot = new BooleanQueryBuilder($properties);
        $this->filter = new BooleanQueryBuilder($properties);
        $this->should = new BooleanQueryBuilder($properties);
    }

    public function must(): BooleanQueryBuilder
    {
        return $this->must;
    }

    public function mustNot(): BooleanQueryBuilder
    {
        return $this->mustNot;
    }

    public function addRaw(string $key, mixed $value): void
    {
        $this->raw[$key] = $value;
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
        $res = array_filter([
            'must' => $this->must->toRaw(),
            'must_not' => $this->mustNot->toRaw(),
            'should' => $this->should->toRaw(),
            'filter' => $this->filter->toRaw(),
        ]);

        $res['boost'] = $this->boost;

        return ['bool' => merge_named($res, $this->raw)];
    }
}
