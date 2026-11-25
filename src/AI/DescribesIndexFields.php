<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Mappings\Types\FlatObject;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\Id;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\Parser;

/**
 * Shared field introspection for the index tools. Turns an index's properties() into either
 * human-readable field lines (for {@see SigmieIndexTool}'s description) or a structured field
 * list (for {@see SigmieIndexSchemaTool}). Private fields ({@see GuardsPrivateFields}) are marked
 * filter-only and left out of the field lists an analytics widget groups or measures by.
 * The using class must expose `protected SigmieIndex $index`.
 */
trait DescribesIndexFields
{
    use GuardsPrivateFields;

    /**
     * Structured per-field schema: name, type, capabilities, description and a filter example.
     *
     * @param  array<int, Type>|null  $fields
     * @param  string  $parentPath  full path of the enclosing nested field, whose sub-fields are named relative to it
     * @return list<array<string, mixed>>
     */
    protected function fieldsSchema(?array $fields = null, string $prefix = '', string $parentPath = ''): array
    {
        $fields ??= $this->index->properties()->get()->toArray();

        $schema = [];

        foreach ($fields as $field) {
            $name = $prefix !== '' ? sprintf('%s.%s', $prefix, $field->name) : $field->name;

            if ($field instanceof Object_) {
                $schema = [...$schema, ...$this->fieldsSchema($this->queryableChildren($field), $name, $parentPath)];

                continue;
            }

            $type = $this->fieldTypeName($field);

            if ($type === null) {
                continue;
            }

            $path = $parentPath !== '' ? sprintf('%s.%s', $parentPath, $name) : $name;
            $private = $this->isPrivateField($path);

            $filter = $this->filterExample($field, $name);
            $entry = [
                'name' => $name,
                'type' => $type,
                'filterable' => $filter !== '' && $filter !== 'query only',
                'sortable' => ! $private && $this->fieldSortable($field),
                'facetable' => ! $private && $this->fieldFacetable($field),
                'filter' => $filter,
            ];

            if ($private) {
                $entry['filter_only'] = true;
            }

            $description = $field->getDescription();

            if (is_string($description) && $description !== '') {
                $entry['description'] = $description;
            }

            if ($field instanceof Nested) {
                $entry['subfields'] = $this->fieldsSchema($field->getProperties()->toArray(), '', $path);
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
                $names = [...$names, ...$this->fieldNamesOfTypes($types, $this->queryableChildren($field), $name)];

                continue;
            }

            if (in_array($this->fieldTypeName($field), $types, true) && ! $this->isPrivateField($name)) {
                if ($field instanceof Text && $field->keywordName() === null && in_array('keyword', $types, true)) {
                    continue;
                }

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
                        $this->queryableChildren($field),
                        $name
                    ),
                ];

                continue;
            }

            $desc = $this->describeField($field, $name, $name);

            if ($desc !== null) {
                $descriptions[] = $desc;
            }
        }

        return $descriptions;
    }

    private function describeField(Type $field, string $name, string $path): ?string
    {
        $type = $this->fieldTypeName($field);

        // @codeCoverageIgnoreStart
        if ($type === null) {
            return null;
        }

        // @codeCoverageIgnoreEnd

        $capabilities = $this->isPrivateField($path) ? ['filter only'] : $this->fieldCapabilities($field);
        $filter = $this->filterExample($field, $name);

        $tags = $capabilities !== [] ? ' ('.implode(', ', $capabilities).')' : '';

        if ($field instanceof Nested) {
            return $this->describeNested($field, $name, $path, $tags, $filter);
        }

        $line = sprintf('- %s [%s]%s: %s', $name, $type, $tags, $filter);

        $description = $field->getDescription();

        return is_string($description) && $description !== ''
            ? $line.' — '.$description
            : $line;
    }

    private function describeNested(Nested $field, string $name, string $path, string $tags, string $filter): string
    {
        $subFields = $this->nestedFieldLines($field->getProperties()->toArray(), $path);

        $desc = sprintf('- %s [nested]%s: %s', $name, $tags, $filter !== '' ? $filter : 'query only');
        $description = $field->getDescription();

        if (is_string($description) && $description !== '') {
            $desc .= ' — '.$description;
        }

        if ($subFields !== []) {
            $desc .= "\n  Sub-fields:\n".implode("\n", array_map(
                fn (string $line): string => '  '.$line,
                $subFields
            ));
        }

        return $desc;
    }

    /**
     * @param  array<int, Type>  $fields
     * @return list<string>
     */
    private function nestedFieldLines(array $fields, string $path, string $prefix = ''): array
    {
        $lines = [];

        foreach ($fields as $child) {
            $name = $prefix !== '' ? $prefix.'.'.$child->name : $child->name;

            if ($child instanceof Object_) {
                $lines = [...$lines, ...$this->nestedFieldLines($this->queryableChildren($child), $path, $name)];

                continue;
            }

            $line = $this->describeField($child, $name, $path.'.'.$name);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /** @return array<array-key, Type> */
    private function queryableChildren(Object_ $field): array
    {
        return $field->isEnabled() ? $field->getProperties()->toArray() : [];
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
            $field instanceof DateTime,
            $field->type() === 'date_nanos' => 'date',
            $field instanceof GeoPoint => 'geo',
            $field instanceof Range => 'range',
            $field instanceof FlatObject => 'flat_object',
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

        if ($this->fieldFacetable($field)) {
            $capabilities[] = 'facetable';
        }

        return $capabilities;
    }

    private function fieldSortable(Type $field): bool
    {
        if (preg_match('/[:\s]/u', $field->fullPath())) {
            return false;
        }

        return match (true) {
            $field instanceof Text => $field->isSortable(),
            $field instanceof Nested, $field instanceof Range => false,
            default => true,
        };
    }

    private function fieldFacetable(Type $field): bool
    {
        $path = $field->fullPath();

        return $field->isFacetable() && (new FacetParser)->fields($path) === [$path];
    }

    private function filterExample(Type $field, string $name): string
    {
        if (! preg_match('/\A'.Parser::FIELD_PATH.'\z/u', $field->fullPath())) {
            return 'query only';
        }

        return match (true) {
            $field instanceof Id => sprintf('%s:42 %s:[1,2] %s>10', $name, $name, $name),
            $field instanceof Keyword,
            $field instanceof CaseSensitiveKeyword => sprintf("%s:'value' %s:['a','b'] %s:val*", $name, $name, $name),
            $field instanceof Number,
            $field instanceof Price => sprintf('%s>n %s<=n %s:min..max', $name, $name, $name),
            $field instanceof FlatObject => sprintf("%s:'value'", $name),
            $field instanceof Boolean => sprintf('%s:true %s:false', $name, $name),
            $field instanceof Date,
            $field instanceof DateTime,
            $field->type() === 'date_nanos' => sprintf("%s>'2024-01-01' %s<'2024-12-31'", $name, $name),
            $field instanceof GeoPoint => $name.':10km[lat,lon]',
            $field instanceof Range => match ($field->type()) {
                'date_range' => sprintf("%s>'2024-01-01'", $name),
                'ip_range' => sprintf("%s:'192.0.2.1'", $name),
                default => sprintf('%s>n %s:min..max', $name, $name),
            },
            $field instanceof Text => $field->isFilterable()
                ? sprintf("%s:'value' %s:['a','b']", $name, $name)
                : 'query only',
            $field instanceof Nested => $this->nestedFilterExample($field, $name),
            default => '',
        };
    }

    private function nestedFilterExample(Nested $field, string $name): string
    {
        $clauses = $this->nestedFilterClauses($field->getProperties()->toArray());

        return $clauses === [] ? $name.':*' : $name.':{'.implode(' AND ', $clauses).'}';
    }

    /**
     * @param  array<int, Type>  $fields
     * @return list<string>
     */
    private function nestedFilterClauses(array $fields, string $prefix = ''): array
    {
        $clauses = [];

        foreach ($fields as $child) {
            $childName = $prefix !== '' ? $prefix.'.'.$child->name : $child->name;

            if ($child instanceof Object_) {
                $clauses = [...$clauses, ...$this->nestedFilterClauses($this->queryableChildren($child), $childName)];

                continue;
            }

            $example = $this->filterExample($child, $childName);
            if ($example === '') {
                continue;
            }

            if ($example === 'query only') {
                continue;
            }

            $clauses[] = $child instanceof Nested ? $example : explode(' ', $example, 2)[0];
        }

        return $clauses;
    }
}
