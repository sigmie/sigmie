<?php

declare(strict_types=1);

namespace Sigmie\Search\Formatters;

use InvalidArgumentException;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Document\Hit;
use Sigmie\Document\RerankedHit;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\HTML;
use Sigmie\Search\NewRerank;
use Sigmie\Search\QueryString;
use Sigmie\Search\SearchContext;

use function Sigmie\Functions\object_map;
use function Sigmie\Functions\response_value;
use function Sigmie\Functions\search_hits;
use function Sigmie\Functions\search_summary;
use function Sigmie\Functions\search_total;
use function Sigmie\Functions\trim_text;

class SigmieSearchResponse extends AbstractFormatter
{
    public function __construct(
        protected Properties $properties,
        protected bool $semantic = false
    ) {}

    public function json(?string $key = null): mixed
    {
        return response_value($this->format(), $key);
    }

    public function format(): array
    {
        $summary = search_summary($this->queryResponseRaw, $this->formatHit(...));
        $size = $this->search->size;

        return [
            ...$summary,
            'code' => $this->code(),
            'semantic' => $this->semantic,
            'query_strings' => array_map(fn ($qs): string => (string) $qs, $this->search->queryStrings ?? []),
            'filter_string' => $this->search->filterString ?? '',
            'facets_string' => $this->search->facetString ?? '',
            'sort_string' => $this->search->sortString ?? '',
            'page' => match (true) {
                $this->search->searchAfter !== null => null,
                $size > 0 => intdiv($this->search->from, $size) + 1,
                default => 1,
            },
            'per_page' => $size,
            'total_pages' => match (true) {
                $this->search->collapseField !== null,
                $summary['total'] === null,
                $summary['total_relation'] === 'gte',
                $summary['terminated_early'] ?? false => null,
                $size > 0 => intdiv($summary['total'], $size) + (int) ($summary['total'] % $size !== 0),
                default => 1,
            },

            'facets' => object_map($this->formatFacets()),
            'errors' => $this->errors,

            'autocomplete' => $this->queryResponseRaw['suggest']['autocompletion'] ?? [],
            // 'params' => $this->context->params ?? [],
        ];
    }

    /**
     * The raw hits as json('hits') and hits() both return them.
     */
    protected function formattedHits(): array
    {
        return array_map($this->formatHit(...), search_hits($this->queryResponseRaw));
    }

    /**
     * Replaces the raw, per-clause nested `inner_hits` with `_matches`, merged per requested path.
     * Other inner hits, such as the `top` hits of uniqueBy(), stay as they are.
     */
    protected function formatHit(array $hit): array
    {
        $hit = $this->search->innerHits->formatRawHit($hit);

        return Hit::formatHighlights($this->formatHtmlHighlights($hit));
    }

    protected function formatHtmlHighlights(array $hit): array
    {
        $tags = $this->search->highlightTags;
        if ($tags === null || $tags[0] === '' || $tags[1] === '') {
            return $hit;
        }

        foreach ($hit['highlight'] ?? [] as $field => $fragments) {
            $type = $this->properties->get((string) $field);
            if ($type instanceof HTML) {
                $hit['highlight'][$field] = array_map(
                    fn (string $fragment): string => $type->formatHighlight($fragment, ...$tags),
                    $fragments,
                );
            }
        }

        foreach ($hit['inner_hits'] ?? [] as $name => $group) {
            if (isset($group['hits']['hits'])) {
                $hit['inner_hits'][$name]['hits']['hits'] = array_map($this->formatHtmlHighlights(...), $group['hits']['hits']);
            }
        }

        return $hit;
    }

    public function autocompletion(): array
    {
        return $this->queryResponseRaw['suggest']['autocompletion'] ?? [];
    }

    public function hits(): array
    {
        return array_map(Hit::fromRaw(...), $this->formattedHits());
    }

    /**
     * rest_total_hits_as_int sends the count. The object shape is {value, relation}.
     */
    public function total(): ?int
    {
        return search_total($this->queryResponseRaw['hits']['total'] ?? null);
    }

    public function getContext(): ?SearchContext
    {
        return $this->search ?? null;
    }

    /**
     * Rerank this response's hits using the first nonblank search query unless you pass an explicit query.
     *
     * @param  array<int, string>  $fields  Source fields to send to the reranker (YAML snippets)
     * @return array<int, RerankedHit>
     */
    public function rerank(
        RerankApi|string $reranker,
        array $fields,
        ?string $query = null,
        ?int $topK = null,
    ): array {
        $api = $this->resolveRerankApi($reranker);
        $queryString = $query ?? $this->defaultRerankQuery();
        $limit = $topK ?? ($this->search->size > 0 ? $this->search->size : 10);

        return (new NewRerank($api))
            ->fields($fields)
            ->query($queryString)
            ->topK($limit)
            ->rerank($this->hits());
    }

    protected function resolveRerankApi(RerankApi|string $reranker): RerankApi
    {
        if ($reranker instanceof RerankApi) {
            return $reranker;
        }

        $api = $this->apis[$reranker] ?? null;
        if (! $api instanceof RerankApi) {
            throw new InvalidArgumentException(
                sprintf('Registered API "%s" is not a RerankApi.', $reranker)
            );
        }

        return $api;
    }

    protected function defaultRerankQuery(): string
    {
        foreach ($this->search->queryStrings as $qs) {
            if ($qs instanceof QueryString && trim_text($qs->text()) !== '') {
                return $qs->text();
            }
        }

        return '';
    }

    public function formatFacets(): array
    {
        $facets = [];

        foreach ($this->search->facetFields as $field) {
            $facet = $this->facet($field);

            if (! is_null($facet)) {
                $facets[$field] = $facet;
            }
        }

        return $facets;
    }

    /**
     * FacetParser wraps each facet as `filter[name] -> field aggs`, and nested
     * fields as `nested[name] -> filter[name] -> field aggs`. Additional
     * ancestor scopes wrap that path under `_nested`.
     */
    public function facet(string $field): array|\stdClass|null
    {
        $type = $this->properties->get($field);

        if (is_null($type) || ! $type->isFacetable()) {
            return null;
        }

        if (isset($this->search) && ! in_array($type->name(), $this->search->facetFields, true)) {
            return null;
        }

        $aggregations = $this->facetAggregations()[$type->name()] ?? [];

        if (! is_null($type->nestedPath())) {
            while (isset($aggregations['_nested'])) {
                $aggregations = $aggregations['_nested'];
            }

            $aggregations = $aggregations[$type->name()] ?? [];
        }

        $counts = $type->facets($aggregations);

        // A term "0" is the only key, so the map is a PHP list.
        if (is_array($counts) && $counts !== [] && array_is_list($counts)) {
            return (object) $counts;
        }

        return $counts;
    }
}
