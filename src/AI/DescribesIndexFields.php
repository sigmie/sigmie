<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;

/**
 * Shared field introspection for the index tools. Turns an index's properties() into either
 * human-readable field lines (for {@see SigmieIndexTool}'s description) or a structured field
 * list (for {@see SigmieIndexSchemaTool}). The using class must expose `protected SigmieIndex $index`.
 */
trait DescribesIndexFields
{
    /**
     * Structured per-field schema: name, type, capabilities, description and a filter example.
     *
     * @param  array<int, Type>|null  $fields
     * @return list<array<string, mixed>>
     */
    protected function fieldsSchema(?array $fields = null, string $prefix = ''): array
    {
        $fields ??= $this->index->properties()->get()->toArray();

        $schema = [];

        foreach ($fields as $field) {
            $name = $prefix !== '' ? sprintf('%s.%s', $prefix, $field->name) : $field->name;

            if ($field instanceof Object_) {
                $schema = [...$schema, ...$this->fieldsSchema($field->getProperties()->toArray(), $name)];

                continue;
            }

            $type = $this->fieldTypeName($field);

            if ($type === null) {
                continue;
            }

            $entry = [
                'name' => $name,
                'type' => $type,
                'filterable' => $this->fieldFilterable($field),
                'sortable' => $this->fieldSortable($field),
                'facetable' => $field->isFacetable(),
                'filter' => $this->filterExample($field, $name),
            ];

            $description = $field->getDescription();

            if (is_string($description) && $description !== '') {
                $entry['description'] = $description;
            }

            if ($field instanceof Nested) {
                $entry['subfields'] = $this->fieldsSchema($field->getProperties()->toArray());
            }

            $schema[] = $entry;
        }

        return $schema;
    }

    /**
     * Flattened names of every field whose type is one of $types — e.g. ['date'] for the timeline
     * fields an analytics widget can bucket by, ['number'] for the fields it can measure.
     *
     * @param  array<int, Type>|null  $fields
     * @param  list<string>  $types
     * @return list<string>
     */
    protected function fieldNamesOfTypes(array $types, ?array $fields = null, string $prefix = ''): array
    {
        $fields ??= $this->index->properties()->get()->toArray();

        $names = [];

        foreach ($fields as $field) {
            $name = $prefix !== '' ? sprintf('%s.%s', $prefix, $field->name) : $field->name;

            if ($field instanceof Object_) {
                $names = [...$names, ...$this->fieldNamesOfTypes($types, $field->getProperties()->toArray(), $name)];

                continue;
            }

            if (in_array($this->fieldTypeName($field), $types, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  array<int, Type>  $fields
     * @return list<string>
     */
    private function collectFieldDescriptions(array $fields, string $prefix = ''): array
    {
        $descriptions = [];

        foreach ($fields as $field) {
            $name = $prefix !== '' ? sprintf('%s.%s', $prefix, $field->name) : $field->name;

            if ($field instanceof Object_) {
                $descriptions = [
                    ...$descriptions,
                    ...$this->collectFieldDescriptions(
                        $field->getProperties()->toArray(),
                        $name
                    ),
                ];

                continue;
            }

            $desc = $this->describeField($field, $name);

            if ($desc !== null) {
                $descriptions[] = $desc;
            }
        }

        return $descriptions;
    }

    private function describeField(Type $field, string $name): ?string
    {
        $type = $this->fieldTypeName($field);

        // @codeCoverageIgnoreStart
        if ($type === null) {
            return null;
        }

        // @codeCoverageIgnoreEnd

        $capabilities = $this->fieldCapabilities($field);
        $filter = $this->filterExample($field, $name);

        $tags = $capabilities !== [] ? ' ('.implode(', ', $capabilities).')' : '';

        if ($field instanceof Nested) {
            return $this->describeNested($field, $name, $tags);
        }

        $line = sprintf('- %s [%s]%s: %s', $name, $type, $tags, $filter);

        $description = $field->getDescription();

        return is_string($description) && $description !== ''
            ? $line.' — '.$description
            : $line;
    }

    private function describeNested(Nested $field, string $name, string $tags): string
    {
        $subFields = array_filter(array_map(
            fn (Type $child): ?string => $this->describeField($child, $child->name),
            $field->getProperties()->toArray()
        ));

        $desc = sprintf("- %s [nested]%s: %s:{subfield:'value' AND other>10}", $name, $tags, $name);

        if ($subFields !== []) {
            $desc .= "\n  Sub-fields:\n".implode("\n", array_map(
                fn (string $line): string => '  '.$line,
                $subFields
            ));
        }

        return $desc;
    }

    private function fieldTypeName(Type $field): ?string
    {
        return match (true) {
            $field instanceof Keyword,
            $field instanceof CaseSensitiveKeyword => 'keyword',
            $field instanceof Number,
            $field instanceof Price => 'number',
            $field instanceof Boolean => 'boolean',
            $field instanceof Date,
            $field instanceof DateTime => 'date',
            $field instanceof GeoPoint => 'geo',
            $field instanceof Range => 'range',
            $field instanceof Nested => 'nested',
            $field instanceof Text => 'text',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function fieldCapabilities(Type $field): array
    {
        $capabilities = [];

        if ($this->fieldSortable($field)) {
            $capabilities[] = 'sortable';
        }

        if ($field->isFacetable()) {
            $capabilities[] = 'facetable';
        }

        return $capabilities;
    }

    private function fieldSortable(Type $field): bool
    {
        return match (true) {
            $field instanceof Text => $field->isSortable(),
            $field instanceof Nested, $field instanceof Range => false,
            default => true,
        };
    }

    private function fieldFilterable(Type $field): bool
    {
        $example = $this->filterExample($field, 'x');

        return $example !== '' && $example !== 'query only';
    }

    private function filterExample(Type $field, string $name): string
    {
        return match (true) {
            $field instanceof Keyword,
            $field instanceof CaseSensitiveKeyword => sprintf("%s:'value' %s:['a','b'] %s:val*", $name, $name, $name),
            $field instanceof Number,
            $field instanceof Price => sprintf('%s>n %s<=n %s:min..max', $name, $name, $name),
            $field instanceof Boolean => sprintf('%s:true %s:false', $name, $name),
            $field instanceof Date,
            $field instanceof DateTime => sprintf("%s>'2024-01-01' %s<'2024-12-31'", $name, $name),
            $field instanceof GeoPoint => $name.':10km[lat,lon]',
            $field instanceof Range => sprintf('%s>n %s:min..max', $name, $name),
            $field instanceof Text => $field->isFilterable()
                ? sprintf("%s:'value' %s:['a','b']", $name, $name)
                : 'query only',
            default => '',
        };
    }
}
