<?php

declare(strict_types=1);

namespace Sigmie\AI;

use InvalidArgumentException;

/**
 * Keeps the fields of {@see \Sigmie\SigmieIndex::exceptFromTools()} filter-only for the agent.
 *
 * `_source` stripping hides private values in documents, but an aggregation, facet, group or sort
 * on the field would still return them (as bucket keys, min/max, or ordering). Every tool passes
 * the field names the agent chose for those roles through {@see refusePrivateFields()} before it
 * queries. Filters are never checked: restricting results by a private value returns no value.
 * The using class must expose `protected SigmieIndex $index`.
 */
trait GuardsPrivateFields
{
    /**
     * @throws InvalidArgumentException when a field is private or a descendant of a private field
     */
    protected function refusePrivateFields(?string ...$fields): void
    {
        foreach ($fields as $field) {
            $field = trim((string) $field);

            if ($field !== '' && $this->isPrivateField($field)) {
                throw new InvalidArgumentException(sprintf(
                    'Field %s is private and cannot be listed, grouped, faceted or sorted; you can still filter on it.',
                    $field
                ));
            }
        }
    }

    /**
     * Field names of a space-separated sort or facet expression, e.g. "price:asc location[1,2]:km:asc".
     *
     * @return list<string>
     */
    protected function expressionFields(?string $expression): array
    {
        $tokens = preg_split('/\s+/', trim((string) $expression), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(static fn (string $token): string => (string) preg_replace('/[:\[].*$/', '', $token), $tokens);
    }

    protected function isPrivateField(string $field): bool
    {
        foreach ($this->index->exceptFromTools() as $private) {
            if ($field === $private || str_starts_with($field, $private.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * One description line naming the private fields, or '' when the index has none.
     */
    protected function privateFieldsNote(): string
    {
        $private = $this->index->exceptFromTools();

        return $private === [] ? '' : sprintf(
            "\n\nPrivate fields (filter only; never listed, grouped, faceted or sorted): %s.",
            implode(', ', $private)
        );
    }
}
