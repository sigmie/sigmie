<?php

declare(strict_types=1);

namespace Sigmie\Document;

class Hit extends Document
{
    // @phpstan-ignore-line

    /**
     * @param  array<string, array{total: int, items: list<array<string, mixed>>}>  $_matches  the matching nested items per path, from {@see \Sigmie\Search\NewSearch::innerHits()}
     */
    public function __construct(
        array $_source,
        string $_id,
        public ?float $_score,
        ?string $_index = null,
        public readonly ?array $sort = null,
        public readonly array $_matches = [],
    ) {
        parent::__construct($_source, $_id);

        $this->index($_index);
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
            '_id' => $this->_id ?? null,
            '_score' => $this->_score,
            '_source' => $this->_source,
            ...($this->_matches === [] ? [] : ['_matches' => $this->_matches]),
        ];
    }
}
