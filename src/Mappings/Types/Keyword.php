<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\Analysis\Normalizer\Normalizer;
use Sigmie\Index\Analysis\NormalizerFilter\Lowercase;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\Normalizer as NormalizerInterface;
use Sigmie\Mappings\Traits\HasFacets;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;

class Keyword extends Type
{
    use HasFacets;

    protected string $type = 'keyword';

    public function toRaw(): array
    {
        $normalizer = $this->normalizer();

        $raw = parent::toRaw();

        if (! is_null($normalizer)) {
            $raw[$this->name]['normalizer'] = $normalizer->name();
        }

        return $raw;
    }

    public function normalizer(): ?NormalizerInterface
    {
        return new Normalizer(
            $this->name.'_field_normalizer',
            filters: [new Lowercase]
        );
    }

    public function handleNormalizer(Analysis $analysis): void
    {
        $normalizer = $this->normalizer();

        if ($normalizer instanceof NormalizerInterface) {
            $analysis->addNormalizer($normalizer);
        }
    }

    public function queries(array|string $queryString): array
    {
        return [new Term($this->name, $queryString), new Prefix($this->name, $queryString)];
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

    public function facets(array $aggregation): ?array
    {
        $originalBuckets = $aggregation[$this->name()][$this->name()][$this->name()]['buckets'] ?? $aggregation[$this->name()][$this->name()]['buckets'] ?? [];

        return array_column($originalBuckets, 'doc_count', 'key');
    }

    public function isFacetable(): bool
    {
        return true;
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_string($value)) {
            return [false, sprintf('The field %s mapped as %s must be a string', $key, $this->typeName())];
        }

        return [true, ''];
    }
}
