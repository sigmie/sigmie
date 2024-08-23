<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Index\Analysis\Normalizer\Normalizer;
use Sigmie\Index\Analysis\NormalizerFilter\Lowercase;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\Normalizer as NormalizerInterface;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;

class Keyword extends Type
{
    protected string $type = 'keyword';

    public function toRaw(): array
    {
        $normalizer = $this->normalizer();

        $raw = parent::toRaw();

        if (!is_null($normalizer)) {
            $raw[$this->name]['normalizer'] = $normalizer->name();
        }

        return $raw;
    }

    public function normalizer(): null|NormalizerInterface
    {
        return new Normalizer(
            "{$this->name}_field_normalizer",
            filters: [new Lowercase]
        );
    }

    public function handleNormalizer(Analysis $analysis)
    {
        $normalizer = $this->normalizer();

        if ($normalizer instanceof NormalizerInterface) {
            $analysis->addNormalizer($normalizer);
        }
    }

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

        $aggregation->size((int)$size);

        if (in_array($order, ['asc', 'desc'])) {
            $aggregation->order('_key', $order);
        }
    }

    public function facets(ElasticsearchResponse $response): null|array
    {
        $json = $response->json();
        $originalBuckets = $json['aggregations'][$this->name()][$this->name()]['buckets'] ?? $json['aggregations'][$this->name()]['buckets'] ?? [];

        return array_column($originalBuckets, 'doc_count', 'key');
    }

    public function isFacetable(): bool
    {
        return true;
    }
}
