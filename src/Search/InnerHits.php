<?php

declare(strict_types=1);

namespace Sigmie\Search;

use InvalidArgumentException;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Nested;

use function Sigmie\Functions\hit_score;
use function Sigmie\Functions\merge_named;
use function Sigmie\Functions\object_map;

/**
 * Which nested items a search returns per hit: the items of the requested nested paths that
 * matched the query or a nested filter (Elasticsearch `inner_hits`).
 *
 * One search can hold several nested clauses on the same path (one per queried nested field,
 * plus each nested filter). Each clause gets a stable name in request order, and
 * {@see matches()} merges them back per path:
 *
 *   query "battery" -> nested reviews (comment match) -> reviews#12 -> items 0, 1
 *   filter reviews:{stars>=4} -> nested reviews    -> reviews#40 -> items 0, 2
 *                                                            merged -> items 0, 1, 2
 *
 * A child clause inside a nested parent returns its hits under that parent. If only the
 * child is requested, the parent gets source-less inner hits named "{path}#{id}#parent".
 */
class InnerHits
{
    /**
     * `index.max_inner_result_window`, the Elasticsearch default cap for inner hits per clause.
     */
    public const MAX_SIZE = 100;

    /**
     * Every request in call order: the fields and the size they share.
     *
     * @var list<array{fields: list<string>, size: int}>
     */
    protected array $requests = [];

    /**
     * Nested path => `_source` includes for its items.
     *
     * @var array<string, list<string>>
     */
    protected array $paths = [];

    /**
     * Nested path => items returned per hit.
     *
     * @var array<string, int>
     */
    protected array $sizes = [];

    protected array $except = [];

    /** @var array<string, array{string, bool}> Names emitted for this request. */
    private array $clauses = [];

    /**
     * Adds a request; earlier requests stay. A later request for the same nested path sets that
     * path's size, and its fields merge with the earlier ones.
     *
     * @param  list<string>  $fields  nested paths (whole items) or fields inside them, in dot syntax
     */
    public function request(array $fields, int $size): void
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Inner hits size must be at least 1.');
        }

        $this->requests[] = ['fields' => $fields, 'size' => $size];
    }

    public function except(array $fields): void
    {
        $this->except = array_values($fields);
    }

    public function requested(): bool
    {
        return $this->requests !== [];
    }

    /**
     * formatRawHit casts a path list to an object. Hit stores the map as an array.
     */
    public static function matchList(mixed $matches): array
    {
        if ($matches instanceof \stdClass) {
            return get_object_vars($matches);
        }

        return is_array($matches) ? $matches : [];
    }

    /**
     * Replaces the raw, per-clause nested `inner_hits` with `_matches`.
     * Other inner hits, such as the `top` hits of uniqueBy(), stay as they are.
     *
     * @param  array<string, mixed>  $hit
     * @return array<string, mixed>
     */
    public function formatRawHit(array $hit): array
    {
        if (! $this->requested()) {
            return $hit;
        }

        $nested = array_filter(
            $hit['inner_hits'] ?? [],
            fn (string|int $name): bool => $this->clausePath((string) $name) !== null,
            ARRAY_FILTER_USE_KEY,
        );

        // Paths 0 and 1 are a PHP list. json_encode would drop those path names.
        $hit['_matches'] = object_map($this->matches($nested));
        $hit['inner_hits'] = array_diff_key($hit['inner_hits'] ?? [], $nested);

        foreach ($hit['inner_hits'] as $name => $group) {
            foreach ($group['hits']['hits'] ?? [] as $index => $member) {
                if (array_intersect_key($member['inner_hits'] ?? [], $this->clauses) !== []) {
                    $hit['inner_hits'][$name]['hits']['hits'][$index] = $this->formatRawHit($member);
                }
            }
        }

        if ($hit['inner_hits'] === []) {
            unset($hit['inner_hits']);
        }

        return $hit;
    }

    /**
     * Maps every requested field to its closest nested ancestor. A whole-path request covers
     * every field selection under that path.
     *
     * @throws InvalidArgumentException when a field is unknown or not inside a nested field
     */
    public function resolve(Properties $properties): void
    {
        $this->paths = [];
        $this->sizes = [];
        $this->clauses = [];

        foreach ($this->requests as $request) {
            foreach ($request['fields'] as $field) {
                if ($properties->get($field) === null) {
                    throw new InvalidArgumentException(sprintf("Field '%s' does not exist.", $field));
                }

                $path = $this->nestedAncestor($properties, $field)
                    ?? throw new InvalidArgumentException(sprintf("Field '%s' is not a nested field or inside one.", $field));

                $this->paths[$path] = match (true) {
                    $field === $path, ($this->paths[$path] ?? null) === [$path] => [$path],
                    default => [...$this->paths[$path] ?? [], $field],
                };
                $this->sizes[$path] = $request['size'];
            }
        }
    }

    /**
     * The inner_hits clause for a nested query on $path, or null when the path is not requested.
     */
    public function clause(string $path): ?array
    {
        $size = $this->sizes[$path] ?? 0;

        foreach (array_keys($this->paths) as $requested) {
            if (str_starts_with((string) $requested, $path.'.')) {
                // Fetch parents for traversal. matches() applies the parent's display limit.
                $size = max($size, self::MAX_SIZE);
                break;
            }
        }

        if ($size === 0) {
            return null;
        }

        $returned = isset($this->paths[$path]);
        $name = $path.'#'.(count($this->clauses) + 1).($returned ? '' : '#parent');
        $this->clauses[$name] = [$path, $returned];

        if ($returned) {
            return [
                'name' => $name,
                'size' => $size,
                '_source' => ['includes' => $this->paths[$path], 'excludes' => $this->except],
            ];
        }

        return ['name' => $name, 'size' => $size, '_source' => false];
    }

    /**
     * A hit's raw `inner_hits`, merged per requested path.
     *
     * Per path, `items` holds up to the size limit of unique matching items, best score first,
     * each with `_offset` (its position in its own array) and, for deeper paths, `_parents`
     * (the offsets of its parent items). Capped overlapping clauses give a lower bound on
     * the union. Child clauses only see the parents returned by Elasticsearch.
     *
     * @return array<string, array{total: int, items: list<array<string, mixed>>}>
     */
    public function matches(array $innerHits): array
    {
        $found = [];

        $this->collect($innerHits, [], $found);

        $matches = [];

        foreach (array_keys($this->paths) as $path) {
            $items = $found[$path]['items'] ?? [];

            usort($items, fn (array $a, array $b): int => [$b['score'], $a['offsets']] <=> [$a['score'], $b['offsets']]);

            $matches[$path] = [
                'total' => $this->total($found[$path]['counts'] ?? []),
                'items' => array_column(array_slice($items, 0, $this->sizes[$path]), 'item'),
            ];
        }

        return $matches;
    }

    // Sibling offsets are disjoint. A clause total and its descendants may overlap.
    protected function total(array $scope): int
    {
        $children = 0;
        foreach ($scope['children'] ?? [] as $child) {
            $children += $this->total($child);
        }

        return max($scope['total'] ?? 0, $children);
    }

    protected function recordTotal(array &$scope, array $offsets, int $total): void
    {
        foreach ($offsets as $offset) {
            $scope = &$scope['children'][$offset];
        }

        $scope['total'] = max($scope['total'] ?? 0, $total);
    }

    protected function collect(array $innerHits, array $parent, array &$found): void
    {
        foreach ($innerHits as $name => $block) {
            $clause = $this->clausePath((string) $name);
            if ($clause === null) {
                continue;
            }
            [$path, $returned] = $clause;

            if ($returned) {
                $found[$path]['counts'] ??= [];
                $total = $block['hits']['total'] ?? 0;
                $this->recordTotal($found[$path]['counts'], $parent, (int) ($total['value'] ?? $total));
            }

            foreach ($block['hits']['hits'] ?? [] as $hit) {
                [$offsets, $parents] = $this->position($hit['_nested'], $path);
                $key = implode('.', $offsets);

                if ($returned) {
                    $score = hit_score($hit['_score'] ?? 0);

                    if (! isset($found[$path]['items'][$key])) {
                        $this->recordTotal($found[$path]['counts'], $offsets, 1);
                    }

                    $current = $found[$path]['items'][$key]['score'] ?? null;

                    if ($current === null || $current < $score) {
                        $source = is_array($hit['_source'] ?? null) ? $hit['_source'] : [];

                        $placed = ['_offset' => end($offsets)];

                        if ($parents !== []) {
                            $placed['_parents'] = object_map($parents);
                        }

                        $found[$path]['items'][$key] = [
                            'score' => $score,
                            'offsets' => $offsets,
                            'item' => merge_named($placed, $source, $placed),
                        ];
                    }
                }

                $this->collect($hit['inner_hits'] ?? [], $offsets, $found);
            }
        }
    }

    /** @return array{string, bool}|null */
    private function clausePath(string $name): ?array
    {
        return $this->clauses[$name] ?? null;
    }

    /**
     * Offsets along the `_nested` chain, and the parent path => offset pairs above the item.
     *
     * @return array{0: list<int>, 1: array<string, int>}
     */
    protected function position(array $nested, string $requestedPath): array
    {
        $offsets = [];
        $fields = [];
        while ($nested !== []) {
            $fields[] = (string) $nested['field'];
            $offsets[] = (int) $nested['offset'];
            $nested = $nested['_nested'] ?? [];
        }

        // Elasticsearch sends relative fields, including dotted paths through ordinary objects.
        $relative = implode('.', $fields) === $requestedPath;
        $parents = [];
        $path = '';
        foreach ($fields as $index => $field) {
            $path = $relative && $index > 0 ? $path.'.'.$field : $field;
            $parents[$path] = $offsets[$index];
        }

        array_pop($parents);

        return [$offsets, $parents];
    }

    protected function nestedAncestor(Properties $properties, string $field): ?string
    {
        $segments = explode('.', $field);

        while ($segments !== []) {
            $candidate = implode('.', $segments);

            if ($properties->get($candidate) instanceof Nested) {
                return $candidate;
            }

            array_pop($segments);
        }

        return null;
    }
}
