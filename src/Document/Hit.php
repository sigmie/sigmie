<?php

declare(strict_types=1);

namespace Sigmie\Document;

use Sigmie\Search\InnerHits;
use stdClass;

use function Sigmie\Functions\hit_score;
use function Sigmie\Functions\object_map;

class Hit extends Document
{
    // @phpstan-ignore-line

    /**
     * @param  array<string, array{total: int, items: list<array<string, mixed>>}>  $_matches  the matching nested items per path, from {@see \Sigmie\Search\NewSearch::innerHits()}
     */
    public function __construct(
        array $_source,
        ?string $_id,
        public ?float $_score,
        ?string $_index = null,
        public readonly ?array $sort = null,
        public readonly array $_matches = [],
        protected readonly array $metadata = [],
    ) {
        parent::__construct($_source, $_id);

        if ($_index !== null) {
            $this->index($_index);
        }
    }

    public static function formatHighlights(array $hit): array
    {
        foreach (['_source', 'fields', 'highlight'] as $key) {
            if (isset($hit[$key])) {
                $map = (array) $hit[$key];
                $hit[$key] = array_is_list($map) ? (object) $map : $map;
            }
        }

        if (! isset($hit['inner_hits'])) {
            return $hit;
        }

        $groups = (array) $hit['inner_hits'];
        foreach ($groups as $name => $group) {
            if (isset($group['hits']['hits'])) {
                $groups[$name]['hits']['hits'] = array_map(
                    self::formatHighlights(...),
                    $group['hits']['hits'],
                );
            }
        }
        $hit['inner_hits'] = object_map($groups);

        return $hit;
    }

    public static function fromRaw(array $raw): static
    {
        return static::class === self::class && isset($raw['_rerank_score'])
            ? RerankedHit::fromRaw($raw)
            : static::hydrate($raw);
    }

    protected static function hydrate(array $raw): static
    {
        $score = $raw['_score'] ?? null;
        $source = $raw['_source'] ?? null;

        return new static(
            is_array($source) || $source instanceof stdClass ? (array) $source : [],
            $raw['_id'] ?? null,
            hit_score($score),
            $raw['_index'] ?? null,
            $raw['sort'] ?? null,
            InnerHits::matchList($raw['_matches'] ?? []),
            array_diff_key($raw, array_flip(['_source', '_id', '_score', '_index', 'sort', '_matches', '_rerank_score'])),
        );
    }

    /**
     * The matching items of one nested path, or of every requested path keyed by path.
     *
     * A field inside a nested path resolves to that path, so `matches('questions.text')` returns
     * the `questions` items. A path that was not requested returns [].
     *
     * @return list<array<string, mixed>>|array<string, list<array<string, mixed>>>
     */
    public function matches(?string $path = null): array
    {
        if ($path === null) {
            return array_map(fn (array $matches): array => $matches['items'], $this->_matches);
        }

        return $this->_matches[$this->matchesPath($path)]['items'] ?? [];
    }

    /**
     * How many items of the nested path matched, including those beyond the size limit.
     * A path that was not requested returns 0.
     */
    public function matchesTotal(string $path): int
    {
        return $this->_matches[$this->matchesPath($path)]['total'] ?? 0;
    }

    /**
     * The longest requested nested path that $path is or lies inside.
     */
    protected function matchesPath(string $path): string
    {
        $segments = explode('.', $path);

        while (count($segments) > 1 && ! isset($this->_matches[implode('.', $segments)])) {
            array_pop($segments);
        }

        return implode('.', $segments);
    }

    /**
     * `_matches` appears only when the search requested inner hits, so hits of other searches
     * keep their shape.
     */
    public function toArray(): array
    {
        return [
            ...self::formatHighlights($this->metadata),
            ...parent::toArray(),
            ...($this->sort === null ? [] : ['sort' => $this->sort]),
            '_score' => match ($this->_score) {
                INF => 'Infinity',
                -INF => '-Infinity',
                default => $this->_score,
            },
            ...($this->_matches === [] ? [] : ['_matches' => object_map($this->_matches)]),
        ];
    }
}
