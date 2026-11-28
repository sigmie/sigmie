<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Term;

use Sigmie\Query\Queries\Query;

class Terms extends Query
{
    // The default index.max_terms_count, the most terms Elasticsearch accepts in one terms query.
    private const MAX_TERMS = 65536;

    public function __construct(
        protected string $field,
        protected array $values
    ) {}

    public function toRaw(): array
    {
        // Explicit terms have numeric keys. A terms lookup has named parameters.
        $values = is_int(array_key_first($this->values)) ? array_values($this->values) : $this->values;

        if (array_is_list($values) && count($values) > self::MAX_TERMS) {
            // Like a terms query, constant_score gives a match the boost once, whatever the chunk.
            return ['constant_score' => [
                'filter' => ['bool' => ['should' => array_map(
                    fn (array $chunk): array => (new self($this->field, $chunk))->toRaw(),
                    array_chunk($values, self::MAX_TERMS),
                )]],
                'boost' => $this->boost,
            ]];
        }

        $terms = [$this->field => $values];

        if ($this->field !== 'boost') {
            $terms['boost'] = $this->boost;
        } elseif ($this->boost !== 1.0) {
            return $this->wrapWithBoost(['terms' => $terms]);
        }

        return ['terms' => $terms];
    }
}
