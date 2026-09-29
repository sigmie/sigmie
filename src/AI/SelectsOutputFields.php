<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * The `fields` argument of the document tools: which source fields each returned document keeps.
 *
 * The agent's comma-separated list wins; null falls back to {@see \Sigmie\SigmieIndex::toolFields()};
 * an empty result means every field. {@see \Sigmie\SigmieIndex::exceptFromTools()} is applied as a
 * source exclude by the caller, so a private field never returns even when requested.
 * The using class must expose `protected SigmieIndex $index`.
 */
trait SelectsOutputFields
{
    protected function outputFieldsSchema(JsonSchema $schema): mixed
    {
        $default = $this->index->toolFields();

        return $schema->string()->description(sprintf(
            "Comma-separated source fields to return; dotted paths reach nested fields (e.g. 'title,chunks.text'). Pass null for the default: %s.",
            $default === [] ? 'all fields' : implode(',', $default)
        ))->nullable()->required();
    }

    /**
     * @return list<string>
     */
    protected function outputFields(mixed $fields): array
    {
        $requested = array_values(array_filter(
            array_map(trim(...), explode(',', (string) $fields)),
            fn (string $field): bool => $field !== '',
        ));

        return $requested === [] ? $this->index->toolFields() : $requested;
    }
}
