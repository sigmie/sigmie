<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;

class CaseSensitiveKeyword extends Type
{
    protected string $type = 'keyword';

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Term($this->name, $queryString);

        $queries[] = new Prefix($this->name, $queryString);

        return $queries;
    }

    public function aggregation(Aggs $aggs, string $params): void
    {
        $params = explode(',', $params);
        $size = $params[0];
        $order = $params[1] ?? null;

        $aggregation = $aggs->terms($this->name(), $this->name());

        $aggregation->size((int) $size);

        if (in_array($order, ['asc', 'desc'])) {
            $aggregation->order('_key', $order);
        }
    }

    public function facets(ElasticsearchResponse $response): ?array
    {
        $json = $response->json();

        $originalBuckets = $json['aggregations'][$this->name()][$this->name().'_histogram']['buckets'] ?? $json['aggregations'][$this->name().'_histogram']['buckets'] ?? [];

        return array_column($originalBuckets, 'doc_count', 'key');
    }

    public function isFacetable(): bool
    {
        return true;
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_string($value)) {
            return [false, "The field {$key} mapped as {$this->typeName()} must be a string"];
        }

        return [true, ''];
    }
}
