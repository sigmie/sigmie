<?php

declare(strict_types=1);

namespace Sigmie\Search;

use InvalidArgumentException;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Nested;

/**
 * Which nested items a search returns per hit: the items of the requested nested paths that
 * matched the query or a nested filter (Elasticsearch `inner_hits`).
 *
 * One search can hold several nested clauses on the same path (one per queried nested field,
 * plus each nested filter), and every inner_hits name must be unique in the request. Each
 * clause therefore gets its own name, and {@see matches()} merges them back per path:
 *
 *   query "battery" -> nested reviews (comment match) -> reviews#12 -> items 0, 1
 *   filter reviews:{stars>=4} -> nested reviews    -> reviews#40 -> items 0, 2
 *                                                            merged -> items 0, 1, 2
 *
 * A deeper path (orders.items) is only returned by Elasticsearch under the inner hits of its
 * parent clause, so the parent clause gets source-less inner hits named "{path}#{id}#parent".
 */
class InnerHits
{
    /**
     * `index.max_inner_result_window`, the Elasticsearch default cap for inner hits per clause.
     */
    public const MAX_SIZE = 100;

    protected const PARENT_ONLY = 'parent';

    /**
     * @var list<string>
     */
    protected array $fields = [];

    /**
     * Nested path => `_source` includes for its items.
     *
     * @var array<string, list<string>>
     */
    protected array $paths = [];

    protected array $except = [];

    protected int $size = self::MAX_SIZE;

    /**
     * @param  list<string>  $fields  nested paths (whole items) or fields inside them, in dot syntax
     */
    public function request(array $fields, int $size): void
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Inner hits size must be at least 1.');
        }

        $this->fields = $fields;
        $this->size = $size;
    }

    public function except(array $fields): void
    {
        $this->except = $fields;
    }

    public function requested(): bool
    {
        return $this->fields !== [];
    }

    /**
     * Maps every requested field to its closest nested ancestor.
     *
     * @throws InvalidArgumentException when a field is unknown or not inside a nested field
     */
    public function resolve(Properties $properties): void
    {
        $this->paths = [];

        foreach ($this->fields as $field) {
            if ($properties->get($field) === null) {
                throw new InvalidArgumentException(sprintf("Field '%s' does not exist.", $field));
            }

            $path = $this->nestedAncestor($properties, $field)
                ?? throw new InvalidArgumentException(sprintf("Field '%s' is not a nested field or inside one.", $field));

            $this->paths[$path] = match (true) {
                $field === $path, ($this->paths[$path] ?? null) === [$path] => [$path],
                default => [...$this->paths[$path] ?? [], $field],
            };
        }
    }

    /**
     * The inner_hits clause for a nested query on $path, or null when the path is not requested.
     */
    public function clause(string $path, int $id): ?array
    {
        if (isset($this->paths[$path])) {
            return [
                'name' => $path.'#'.$id,
                'size' => $this->size,
                '_source' => ['includes' => $this->paths[$path], 'excludes' => $this->except],
            ];
        }

        foreach (array_keys($this->paths) as $requested) {
            if (str_starts_with($requested, $path.'.')) {
                return ['name' => $path.'#'.$id.'#'.self::PARENT_ONLY, 'size' => $this->size, '_source' => false];
            }
        }

        return null;
    }

    /**
     * A hit's raw `inner_hits`, merged per requested path.
     *
     * Per path, `items` holds up to the size limit of unique matching items, best score first,
     * each with `_offset` (its position in its own array) and, for deeper paths, `_parents`
     * (the offsets of its parent items). `total` counts all matching items, so `total` greater
     * than the number of items means the list is capped.
     *
     * Corner: when several clauses on one path each cap their items, `total` is the largest
     * clause total, a lower bound of the union. It still exceeds the returned items, so the cap
     * stays visible. Deeper paths only count items under the parents Elasticsearch returned.
     *
     * @return array<string, array{total: int, items: list<array<string, mixed>>}>
     */
    public function matches(array $innerHits): array
    {
        $found = [];

        $this->collect($innerHits, '', $found);

        $matches = [];

        foreach (array_keys($this->paths) as $path) {
            $items = $found[$path]['items'] ?? [];

            usort($items, fn (array $a, array $b): int => [$b['score'], $a['offsets']] <=> [$a['score'], $b['offsets']]);

            $total = 0;

            foreach ($found[$path]['contexts'] ?? [] as $context) {
                $total += max($context['total'], $context['count']);
            }

            $matches[$path] = [
                'total' => $total,
                'items' => array_column(array_slice($items, 0, $this->size), 'item'),
            ];
        }

        return $matches;
    }

    protected function collect(array $innerHits, string $parent, array &$found): void
    {
        foreach ($innerHits as $name => $block) {
            $parts = explode('#', (string) $name);
            $path = $parts[0];
            $returned = ($parts[2] ?? null) !== self::PARENT_ONLY;

            if ($returned) {
                $found[$path]['contexts'][$parent]['count'] ??= 0;
                $found[$path]['contexts'][$parent]['total'] = max(
                    $found[$path]['contexts'][$parent]['total'] ?? 0,
                    (int) ($block['hits']['total']['value'] ?? 0),
                );
            }

            foreach ($block['hits']['hits'] ?? [] as $hit) {
                [$offsets, $parents] = $this->position($hit['_nested']);
                $key = implode('.', $offsets);

                if ($returned) {
                    $score = (float) ($hit['_score'] ?? 0);

                    if (! isset($found[$path]['items'][$key])) {
                        $found[$path]['contexts'][$parent]['count']++;
                    }

                    if (($found[$path]['items'][$key]['score'] ?? -1.0) < $score) {
                        $found[$path]['items'][$key] = [
                            'score' => $score,
                            'offsets' => $offsets,
                            'item' => [
                                '_offset' => end($offsets),
                                ...($parents === [] ? [] : ['_parents' => $parents]),
                                ...$hit['_source'] ?? [],
                            ],
                        ];
                    }
                }

                $this->collect($hit['inner_hits'] ?? [], $key, $found);
            }
        }
    }

    /**
     * Offsets along the `_nested` chain, and the parent path => offset pairs above the item.
     *
     * @return array{0: list<int>, 1: array<string, int>}
     */
    protected function position(array $nested): array
    {
        $offsets = [];
        $parents = [];
        $path = '';

        while ($nested !== []) {
            $path = ltrim($path.'.'.$nested['field'], '.');
            $offsets[] = (int) $nested['offset'];
            $parents[$path] = (int) $nested['offset'];
            $nested = $nested['_nested'] ?? [];
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
