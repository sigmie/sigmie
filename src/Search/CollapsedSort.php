<?php

declare(strict_types=1);

namespace Sigmie\Search;

use function Sigmie\Functions\keyed;

final class CollapsedSort
{
    private function __construct(
        public readonly string $field,
        private readonly string $order,
        public readonly bool $missingFirst,
        public readonly bool $customMissing,
    ) {}

    public static function fromSort(?string $field, array $sort): ?self
    {
        if ($field === null || count($sort) !== 1) {
            return null;
        }

        $clause = $sort[0];
        $sortField = is_string($clause) ? $clause : (string) array_key_first((array) $clause);
        if ($sortField !== $field) {
            return null;
        }

        $options = is_string($clause) ? [] : ((array) $clause)[$field];
        // A converted sort value can identify several distinct collapse groups.
        if (isset(((array) $options)['numeric_type'])) {
            return null;
        }

        $order = is_string($options) ? $options : (((array) $options)['order'] ?? 'asc');

        $missing = ((array) $options)['missing'] ?? '_last';

        return new self($field, $order, $missing === '_first', ! in_array($missing, ['_first', '_last'], true));
    }

    public function postFilter(mixed $after, mixed $postFilter, ?bool $passedMissing = null, bool $includeTie = false): array
    {
        $exists = ['exists' => ['field' => $this->field]];

        if ($after === null) {
            $seek = $this->missingFirst ? $exists : ['match_none' => (object) []];
        } else {
            $operator = $this->order === 'desc' ? 'lt' : 'gt';
            $seek = ['range' => keyed($this->field, [($includeTie ? $operator.'e' : $operator) => $after])];
            if ($passedMissing) {
                $seek = ['bool' => ['filter' => [$seek, $exists]]];
            } elseif (! $this->missingFirst || ($passedMissing === false && is_int($after))) {
                $seek = ['bool' => ['should' => [$seek, ['bool' => ['must_not' => [$exists]]]]]];
            }
        }

        return ['bool' => ['filter' => [...($postFilter === null ? [] : [$postFilter]), $seek]]];
    }
}
