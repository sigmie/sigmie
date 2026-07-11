<?php

declare(strict_types=1);

namespace Sigmie\Analytics;

use InvalidArgumentException;
use Sigmie\Mappings\Contracts\FieldContainer;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\SigmieIndex;

class QueryRecipe
{
    /** @var list<string> */
    private const SLOT_TYPES = ['integer', 'number', 'string', 'period', 'date', 'timezone_offset'];

    /** @var list<string> */
    private const SLOT_TARGETS = [
        'limit',
        'range',
        'from',
        'to',
        'timezone_offset',
        'interval',
        'sort',
        'min_count',
        'bucket_size',
        'precision',
        'percents',
    ];

    /** @var list<string> */
    private const FILTER_OPERATORS = ['equals', 'not_equals', 'gt', 'gte', 'lt', 'lte'];

    /**
     * @param  array<string, mixed>  $definition
     */
    private function __construct(private readonly array $definition) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition): self
    {
        $normalized = self::normalize($definition);
        self::validate($normalized);

        return new self($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->definition;
    }

    public function hash(): string
    {
        return hash('sha256', json_encode($this->definition, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $bindings
     */
    public function bind(array $bindings): AnalyticsRequest
    {
        $slots = [];

        foreach ((array) ($this->definition['slots'] ?? []) as $slot) {
            $slots[(string) $slot['name']] = $slot;
        }

        $unknown = array_diff(array_keys($bindings), array_keys($slots));

        $unknown === [] || throw new InvalidArgumentException('Unknown query recipe bindings: '.implode(', ', $unknown).'.');

        $request = (array) $this->definition['template'];
        $resolved = [];

        foreach ($slots as $name => $slot) {
            $hasBinding = array_key_exists((string) $name, $bindings);
            $value = $hasBinding ? $bindings[$name] : ($slot['default'] ?? null);

            if ($value === null || $value === '') {
                ($slot['required'] ?? false) !== true
                    || throw new InvalidArgumentException("Query recipe binding [{$name}] is required.");

                continue;
            }

            $resolved[$name] = self::slotValue($slot, $value);

            if (($slot['target'] ?? null) !== null) {
                $request[$slot['target']] = $resolved[$name];
            }
        }

        $filters = self::boundFilters(
            (array) ($this->definition['filter_templates'] ?? []),
            $resolved,
        );

        if ($filters !== '') {
            $fixed = trim((string) ($request['filters'] ?? ''));
            $request['filters'] = $fixed !== '' ? "({$fixed}) AND ({$filters})" : $filters;
        }

        return AnalyticsRequest::fromArray($request);
    }

    public function validateAgainst(SigmieIndex $index): static
    {
        $fields = self::indexFields($index);
        $template = (array) $this->definition['template'];

        self::requireFieldType($fields, (string) ($template['date_field'] ?? ''), ['date'], 'date_field');

        foreach (['field', 'bucket_field'] as $key) {
            $field = trim((string) ($template[$key] ?? ''));

            if ($field !== '') {
                self::requireField($fields, $field, $key);
            }
        }

        $widget = (string) ($template['widget'] ?? '');
        $metric = (string) ($template['metric'] ?? '');
        $field = (string) ($template['field'] ?? '');

        if ($widget === 'geo') {
            self::requireFieldType($fields, $field, ['geo'], 'field');
        } elseif ($field !== '' && ($metric !== '' && $metric !== 'count' || in_array($widget, ['distribution', 'histogram_metric', 'percentiles', 'stats'], true))) {
            self::requireFieldType($fields, $field, ['number'], 'field');
        }

        if (($template['bucket_field'] ?? '') !== '') {
            self::requireFieldType($fields, (string) $template['bucket_field'], ['number'], 'bucket_field');
        }

        foreach (['group_by', 'row_field', 'col_field', 'id_field'] as $key) {
            $field = trim((string) ($template[$key] ?? ''));

            if ($field !== '') {
                self::requireFieldType($fields, $field, ['keyword', 'text'], $key);
            }
        }

        foreach (['cohort_field'] as $key) {
            $field = trim((string) ($template[$key] ?? ''));

            if ($field !== '') {
                self::requireFieldType($fields, $field, ['date'], $key);
            }
        }

        foreach (self::csvFields((string) ($template['group_by_fields'] ?? '')) as $field) {
            self::requireFieldType($fields, $field, ['keyword', 'text'], 'group_by_fields');
        }

        foreach (self::csvFields((string) ($template['fields'] ?? '')) as $field) {
            self::requireField($fields, $field, 'fields');
        }

        if (($template['sort'] ?? '') !== '') {
            [$sortField, $direction] = array_pad(explode(':', (string) $template['sort'], 2), 2, 'asc');
            self::requireField($fields, trim($sortField), 'sort');
            in_array(strtolower(trim($direction)), ['asc', 'desc'], true)
                || throw new InvalidArgumentException("Unsupported query recipe sort direction [{$direction}].");
        }

        if ($widget === 'grouped_metrics') {
            $metrics = json_decode((string) ($template['metrics'] ?? ''), true);
            is_array($metrics) || throw new InvalidArgumentException('Query recipe grouped metrics must be valid JSON.');

            foreach ($metrics as $groupedMetric) {
                is_array($groupedMetric) || throw new InvalidArgumentException('Query recipe grouped metric must be an object.');
                $groupedMetricName = (string) ($groupedMetric['metric'] ?? '');

                if ($groupedMetricName === 'count') {
                    continue;
                }

                self::requireFieldType($fields, (string) ($groupedMetric['field'] ?? ''), ['number'], 'metrics.field');
            }
        }

        $slots = [];

        foreach ((array) ($this->definition['slots'] ?? []) as $slot) {
            $slots[(string) $slot['name']] = $slot;
        }

        foreach ((array) ($this->definition['filter_templates'] ?? []) as $filter) {
            $filterField = (string) $filter['field'];
            self::requireField($fields, $filterField, 'filter_template');
            self::validateFilterSlotType(
                (string) ($fields[$filterField] ?? ''),
                (string) $filter['operator'],
                (array) ($slots[(string) $filter['slot']] ?? []),
                $filterField,
            );
        }

        return $this;
    }

    public static function contractFingerprint(SigmieIndex $index): string
    {
        $fields = self::indexFields($index);
        ksort($fields);

        return hash('sha256', json_encode([
            'recipe_version' => 1,
            'analytics_widgets' => AnalyticsRequest::WIDGETS,
            'analytics_keys' => AnalyticsRequest::KEYS,
            'fields' => $fields,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private static function normalize(array $definition): array
    {
        $dataset = trim((string) ($definition['dataset'] ?? ''));
        $slots = array_values(array_map(
            fn (array $slot): array => self::normalizeSlot($slot),
            array_filter((array) ($definition['slots'] ?? []), fn (mixed $slot): bool => is_array($slot)),
        ));
        usort($slots, fn (array $left, array $right): int => $left['name'] <=> $right['name']);
        $validationTemplate = (array) ($definition['template'] ?? []);

        foreach ($slots as $slot) {
            $target = $slot['target'] ?? null;

            if ($target !== null && ! array_key_exists($target, $validationTemplate)) {
                $validationTemplate[$target] = self::validationSlotValue($slot, $target);
            }
        }

        $template = AnalyticsRequest::fromArray($validationTemplate)->toArray();

        foreach ($slots as $slot) {
            if (($slot['target'] ?? null) !== null) {
                unset($template[$slot['target']]);
            }
        }

        $filters = array_values(array_map(
            fn (array $filter): array => [
                'field' => trim((string) ($filter['field'] ?? '')),
                'operator' => trim((string) ($filter['operator'] ?? 'equals')),
                'slot' => trim((string) ($filter['slot'] ?? '')),
            ],
            array_filter((array) ($definition['filter_templates'] ?? []), fn (mixed $filter): bool => is_array($filter)),
        ));
        usort($filters, fn (array $left, array $right): int => implode('|', $left) <=> implode('|', $right));

        return [
            'version' => 1,
            'dataset' => $dataset,
            'template' => $template,
            'slots' => $slots,
            'filter_templates' => $filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array<string, mixed>
     */
    private static function normalizeSlot(array $slot): array
    {
        $normalized = [
            'name' => trim((string) ($slot['name'] ?? '')),
            'target' => ($target = trim((string) ($slot['target'] ?? ''))) !== '' ? $target : null,
            'type' => trim((string) ($slot['type'] ?? 'string')),
            'required' => (bool) ($slot['required'] ?? false),
        ];

        if (array_key_exists('default', $slot) && $slot['default'] !== null && $slot['default'] !== '') {
            $normalized['default'] = $slot['default'];
        }

        if (isset($slot['minimum'])) {
            $normalized['minimum'] = (int) $slot['minimum'];
        }

        if (isset($slot['maximum'])) {
            $normalized['maximum'] = (int) $slot['maximum'];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function validate(array $definition): void
    {
        $dataset = (string) ($definition['dataset'] ?? '');
        preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $dataset) === 1
            || throw new InvalidArgumentException("Invalid query recipe dataset [{$dataset}].");

        $slots = (array) $definition['slots'];
        $names = [];
        $targets = [];

        foreach ($slots as $slot) {
            $name = (string) $slot['name'];
            preg_match('/^[a-z][a-z0-9_]*$/', $name) === 1
                || throw new InvalidArgumentException("Invalid query recipe slot name [{$name}].");
            in_array((string) $slot['type'], self::SLOT_TYPES, true)
                || throw new InvalidArgumentException("Unsupported query recipe slot type [{$slot['type']}].");

            if (($slot['target'] ?? null) !== null) {
                in_array((string) $slot['target'], self::SLOT_TARGETS, true)
                    || throw new InvalidArgumentException("Unsupported query recipe slot target [{$slot['target']}].");
                in_array($slot['target'], $targets, true)
                    && throw new InvalidArgumentException("Duplicate query recipe slot target [{$slot['target']}].");
                $targets[] = $slot['target'];
            }

            in_array($name, $names, true)
                && throw new InvalidArgumentException("Duplicate query recipe slot [{$name}].");
            $names[] = $name;

            if (array_key_exists('default', $slot)) {
                self::slotValue($slot, $slot['default']);
            }
        }

        foreach ((array) $definition['filter_templates'] as $filter) {
            $field = (string) $filter['field'];
            $slot = (string) $filter['slot'];
            $field !== '' || throw new InvalidArgumentException('Query recipe filter field is required.');
            in_array((string) $filter['operator'], self::FILTER_OPERATORS, true)
                || throw new InvalidArgumentException("Unsupported query recipe filter operator [{$filter['operator']}].");
            in_array($slot, $names, true)
                || throw new InvalidArgumentException("Query recipe filter references unknown slot [{$slot}].");
        }
    }

    /** @param array<string, mixed> $slot */
    private static function validationSlotValue(array $slot, string $target): int|string
    {
        if (array_key_exists('default', $slot)) {
            return self::slotValue($slot, $slot['default']);
        }

        return match ($target) {
            'limit' => max(1, (int) ($slot['minimum'] ?? 1)),
            'range' => 'last_30_days',
            'from' => '2000-01-01',
            'to' => '2000-02-01',
            'timezone_offset', 'min_count' => 0,
            'interval' => 'day',
            'sort' => 'indexed_at:asc',
            'bucket_size' => 1,
            'precision' => 5,
            'percents' => '50',
            default => throw new InvalidArgumentException("Unsupported query recipe slot target [{$target}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    private static function slotValue(array $slot, mixed $value): int|float|string
    {
        $type = (string) $slot['type'];

        if (in_array($type, ['integer', 'timezone_offset'], true)) {
            is_numeric($value) || throw new InvalidArgumentException("Query recipe binding [{$slot['name']}] must be an integer.");
            $value = (int) $value;
            $minimum = (int) ($slot['minimum'] ?? ($type === 'timezone_offset' ? -840 : PHP_INT_MIN));
            $maximum = (int) ($slot['maximum'] ?? ($type === 'timezone_offset' ? 840 : PHP_INT_MAX));
            ($value >= $minimum && $value <= $maximum)
                || throw new InvalidArgumentException("Query recipe binding [{$slot['name']}] is outside its allowed range.");

            return $value;
        }

        if ($type === 'number') {
            is_numeric($value) || throw new InvalidArgumentException("Query recipe binding [{$slot['name']}] must be numeric.");

            return (float) $value;
        }

        $value = trim((string) $value);
        $value !== '' || throw new InvalidArgumentException("Query recipe binding [{$slot['name']}] cannot be empty.");

        return $value;
    }

    /**
     * @param  list<array<string, string>>  $templates
     * @param  array<string, int|float|string>  $bindings
     */
    private static function boundFilters(array $templates, array $bindings): string
    {
        $filters = [];

        foreach ($templates as $filter) {
            $slot = (string) $filter['slot'];

            if (! array_key_exists($slot, $bindings)) {
                continue;
            }

            $filters[] = self::filterClause(
                (string) $filter['field'],
                (string) $filter['operator'],
                $bindings[$slot],
            );
        }

        return implode(' AND ', $filters);
    }

    private static function filterClause(string $field, string $operator, int|float|string $value): string
    {
        $value = is_int($value) || is_float($value)
            ? (string) $value
            : "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";

        return match ($operator) {
            'equals' => "{$field}:{$value}",
            'not_equals' => "NOT {$field}:{$value}",
            'gt' => "{$field}>{$value}",
            'gte' => "{$field}>={$value}",
            'lt' => "{$field}<{$value}",
            'lte' => "{$field}<={$value}",
            default => throw new InvalidArgumentException("Unsupported query recipe filter operator [{$operator}]."),
        };
    }

    /** @param array<string, mixed> $slot */
    private static function validateFilterSlotType(string $fieldType, string $operator, array $slot, string $field): void
    {
        $slotType = (string) ($slot['type'] ?? '');
        $valid = match ($fieldType) {
            'number' => in_array($slotType, ['integer', 'number'], true),
            'date' => $slotType === 'date',
            'keyword', 'text' => $slotType === 'string' && in_array($operator, ['equals', 'not_equals'], true),
            default => false,
        };

        $valid || throw new InvalidArgumentException("Query recipe filter slot for [{$field}] does not match its field type or operator.");
    }

    /**
     * @return array<string, string>
     */
    private static function indexFields(SigmieIndex $index): array
    {
        return self::fieldTypes($index->properties()->get()->toArray());
    }

    /**
     * @param  array<int|string, Type>  $fields
     * @return array<string, string>
     */
    private static function fieldTypes(array $fields, string $prefix = ''): array
    {
        $types = [];

        foreach ($fields as $field) {
            $name = $prefix !== '' ? "{$prefix}.{$field->name}" : $field->name;
            $types[$name] = self::fieldType($field);

            if ($field instanceof FieldContainer) {
                $types = [
                    ...$types,
                    ...self::fieldTypes($field->getProperties()->toArray(), $name),
                ];
            }
        }

        return $types;
    }

    private static function fieldType(Type $field): string
    {
        return match (true) {
            $field instanceof Number, $field instanceof Price => 'number',
            $field instanceof Date, $field instanceof DateTime => 'date',
            $field instanceof Keyword, $field instanceof CaseSensitiveKeyword => 'keyword',
            $field instanceof Boolean => 'boolean',
            $field instanceof GeoPoint => 'geo',
            $field instanceof Text => 'text',
            default => 'other',
        };
    }

    /**
     * @param  array<string, string>  $fields
     */
    private static function requireField(array $fields, string $field, string $argument): void
    {
        isset($fields[$field])
            || throw new InvalidArgumentException("Query recipe {$argument} field [{$field}] does not exist in the index.");
    }

    /**
     * @param  array<string, string>  $fields
     * @param  list<string>  $types
     */
    private static function requireFieldType(array $fields, string $field, array $types, string $argument): void
    {
        self::requireField($fields, $field, $argument);
        in_array($fields[$field], $types, true)
            || throw new InvalidArgumentException("Query recipe {$argument} field [{$field}] has incompatible type [{$fields[$field]}].");
    }

    /** @return list<string> */
    private static function csvFields(string $fields): array
    {
        return array_values(array_filter(array_map(
            fn (string $field): string => trim($field),
            explode(',', $fields),
        )));
    }
}
