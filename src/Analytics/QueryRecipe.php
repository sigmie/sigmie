<?php

declare(strict_types=1);

namespace Sigmie\Analytics;

use DateTimeImmutable;
use InvalidArgumentException;
use Sigmie\Analytics\Enums\Period;
use Sigmie\Mappings\Contracts\FieldContainer;
use Sigmie\Mappings\NewProperties;
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
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\ParseException;
use Sigmie\Parse\SortParser;
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
        'min_count',
        'bucket_size',
        'precision',
        'percents',
    ];

    /** @var array<string, list<string>> */
    private const SLOT_TARGET_TYPES = [
        'limit' => ['integer'],
        'range' => ['period'],
        'from' => ['date'],
        'to' => ['date'],
        'timezone_offset' => ['timezone_offset'],
        'interval' => ['string'],
        'min_count' => ['integer'],
        'bucket_size' => ['integer'],
        'precision' => ['integer'],
        'percents' => ['string'],
    ];

    /** @var list<string> */
    private const FILTER_OPERATORS = ['equals', 'not_equals', 'gt', 'gte', 'lt', 'lte'];

    /** @var list<string> */
    private const SLOT_KEYS = ['name', 'target', 'type', 'required', 'default', 'minimum', 'maximum'];

    /** @var list<string> */
    private const FILTER_TEMPLATE_KEYS = ['field', 'operator', 'slot'];

    /** @var list<string> */
    private const DEFINITION_KEYS = ['version', 'dataset', 'template', 'slots', 'filter_templates'];

    /** @var list<string> */
    private const LIMIT_WIDGETS = ['grouped_trend', 'breakdown', 'multi_breakdown', 'union_breakdown', 'grouped_metrics', 'table', 'heatmap'];

    /** @var list<string> */
    private const RANKING_WIDGETS = ['breakdown', 'multi_breakdown', 'union_breakdown', 'grouped_metrics'];

    /**
     * @param  array<string, mixed>  $definition
     */
    private function __construct(private readonly array $definition) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition): self
    {
        self::validateDefinitionShape($definition);
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

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown query recipe bindings: '.implode(', ', $unknown).'.');
        }

        $request = (array) $this->definition['template'];
        $resolved = [];

        foreach ($slots as $name => $slot) {
            $hasBinding = array_key_exists($name, $bindings);
            $value = $hasBinding ? $bindings[$name] : ($slot['default'] ?? null);

            if ($value === null || $value === '') {
                if (($slot['required'] ?? false) === true) {
                    throw new InvalidArgumentException(sprintf('Query recipe binding [%s] is required.', $name));
                }

                continue;
            }

            $resolved[$name] = self::slotValue($slot, $value);

            if (($slot['target'] ?? null) !== null) {
                $request[$slot['target']] = $resolved[$name];
            }
        }

        $filters = $this->boundFilters((array) ($this->definition['filter_templates'] ?? []), $resolved);

        if ($filters !== '') {
            $fixed = trim((string) ($request['filters'] ?? ''));
            $request['filters'] = $fixed !== '' ? sprintf('(%s) AND (%s)', $fixed, $filters) : $filters;
        }

        return AnalyticsRequest::fromArray($request);
    }

    public function validateAgainst(SigmieIndex $index): static
    {
        $fields = self::indexFields($index);
        $properties = $index->properties();
        $template = (array) $this->definition['template'];

        $this->requireFieldType($fields, (string) ($template['date_field'] ?? ''), ['date'], 'date_field');

        foreach (['field', 'bucket_field'] as $key) {
            $field = trim((string) ($template[$key] ?? ''));

            if ($field !== '') {
                $this->requireField($fields, $field, $key);
            }
        }

        $widget = (string) ($template['widget'] ?? '');
        $metric = (string) ($template['metric'] ?? '');
        $field = (string) ($template['field'] ?? '');

        if ($widget === 'geo') {
            $this->requireFieldType($fields, $field, ['geo'], 'field');
        } elseif ($field !== '' && ($metric !== '' && $metric !== 'count' || in_array($widget, ['distribution', 'histogram_metric', 'percentiles', 'stats'], true))) {
            $this->requireFieldType($fields, $field, ['number'], 'field');
        }

        if (($template['bucket_field'] ?? '') !== '') {
            $this->requireFieldType($fields, (string) $template['bucket_field'], ['number'], 'bucket_field');
        }

        foreach (['group_by', 'row_field', 'col_field', 'id_field'] as $key) {
            $field = trim((string) ($template[$key] ?? ''));

            if ($field !== '') {
                $this->requireFieldType($fields, $field, ['keyword', 'text', 'number', 'boolean'], $key);
            }
        }

        foreach (['cohort_field'] as $key) {
            $field = trim((string) ($template[$key] ?? ''));

            if ($field !== '') {
                $this->requireFieldType($fields, $field, ['date'], $key);
            }
        }

        $groupByFields = $this->csvFields((string) ($template['group_by_fields'] ?? ''));

        foreach ($groupByFields as $field) {
            $this->requireFieldType($fields, $field, ['keyword', 'text', 'number', 'boolean'], 'group_by_fields');
        }

        if ($widget === 'union_breakdown') {
            $this->validateUnionGroupFields($fields, $groupByFields);
        }

        foreach (['fields', 'hit_fields'] as $key) {
            foreach ($this->csvFields((string) ($template[$key] ?? '')) as $field) {
                $this->requireField($fields, $field, $key);
            }
        }

        if (($template['sort'] ?? '') !== '' && ! in_array($widget, self::RANKING_WIDGETS, true)) {
            $this->validateSort($properties, (string) $template['sort'], 'sort', false);
        }

        if (($template['hit_sort'] ?? '') !== '') {
            $this->validateSort($properties, (string) $template['hit_sort'], 'hit_sort', true);
        }

        if ($widget === 'grouped_metrics') {
            $metrics = json_decode((string) ($template['metrics'] ?? ''), true);
            if (! is_array($metrics)) {
                throw new InvalidArgumentException('Query recipe grouped metrics must be valid JSON.');
            }

            foreach ($metrics as $groupedMetric) {
                if (! is_array($groupedMetric)) {
                    throw new InvalidArgumentException('Query recipe grouped metric must be an object.');
                }

                $groupedMetricName = (string) ($groupedMetric['metric'] ?? '');

                if ($groupedMetricName === 'count') {
                    continue;
                }

                $this->requireFieldType($fields, (string) ($groupedMetric['field'] ?? ''), ['number'], 'metrics.field');
            }
        }

        $slots = [];

        foreach ((array) ($this->definition['slots'] ?? []) as $slot) {
            $slots[(string) $slot['name']] = $slot;
        }

        foreach ((array) ($this->definition['filter_templates'] ?? []) as $filter) {
            $filterField = (string) $filter['field'];
            $this->requireField($fields, $filterField, 'filter_template');
            $this->validateFilterSlotType((string) ($fields[$filterField] ?? ''), (string) $filter['operator'], (array) ($slots[(string) $filter['slot']] ?? []), $filterField);
        }

        foreach (['filters', 'hit_filters'] as $key) {
            $filter = trim((string) ($template[$key] ?? ''));

            if ($filter !== '') {
                $this->validateStaticFilter($properties, $filter, $key);
            }
        }

        if ($widget === 'funnel') {
            $steps = json_decode((string) ($template['steps'] ?? ''), true);

            foreach (is_array($steps) ? $steps : [] as $step) {
                $this->validateStaticFilter($properties, (string) ($step['filter'] ?? ''), 'funnel step filter');
            }
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
            $definition['slots'] ?? [],
        ));
        $validationTemplate = self::normalizeRankingSort($definition['template'] ?? []);
        $limitSlotIndex = null;

        foreach ($slots as $index => $slot) {
            if (($slot['target'] ?? null) === 'limit') {
                $limitSlotIndex = $index;

                break;
            }
        }

        if ($limitSlotIndex !== null && array_key_exists('limit', $validationTemplate) && ! array_key_exists('default', $slots[$limitSlotIndex])) {
            $slots[$limitSlotIndex]['default'] = self::slotValue($slots[$limitSlotIndex], $validationTemplate['limit']);
        }

        $supportsLimit = array_key_exists('limit', $validationTemplate)
            || in_array((string) ($validationTemplate['widget'] ?? ''), self::LIMIT_WIDGETS, true);

        if ($limitSlotIndex === null && $supportsLimit) {
            $limitSlot = [
                'name' => self::availableLimitSlotName($slots),
                'target' => 'limit',
                'type' => 'integer',
                'required' => false,
                'minimum' => 1,
                'maximum' => 100,
            ];

            if (array_key_exists('limit', $validationTemplate)) {
                $limitSlot['default'] = $validationTemplate['limit'];
            }

            $slots[] = self::normalizeSlot($limitSlot);
        }

        usort($slots, fn (array $left, array $right): int => $left['name'] <=> $right['name']);

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
            $definition['filter_templates'] ?? [],
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
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private static function normalizeRankingSort(array $template): array
    {
        if (! in_array((string) ($template['widget'] ?? ''), self::RANKING_WIDGETS, true)) {
            return $template;
        }

        $sort = trim((string) ($template['sort'] ?? ''));
        [, $direction] = array_pad(explode(':', $sort, 2), 2, 'desc');
        $direction = strtolower(trim($direction));

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported query recipe ranking direction [%s].', $direction));
        }

        $template['sort'] = sprintf('metric:%s', $direction);

        return $template;
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

        if (array_key_exists('minimum', $slot) && $slot['minimum'] !== null && $slot['minimum'] !== '') {
            $normalized['minimum'] = self::slotBound($slot['minimum'], $normalized, 'minimum');
        }

        if (array_key_exists('maximum', $slot) && $slot['maximum'] !== null && $slot['maximum'] !== '') {
            $normalized['maximum'] = self::slotBound($slot['maximum'], $normalized, 'maximum');
        }

        if (array_key_exists('default', $slot) && $slot['default'] !== null && $slot['default'] !== '') {
            $normalized['default'] = in_array($normalized['type'], self::SLOT_TYPES, true)
                ? self::slotValue($normalized, $slot['default'])
                : $slot['default'];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function validateDefinitionShape(array $definition): void
    {
        $unknown = array_diff(array_keys($definition), self::DEFINITION_KEYS);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown query recipe definition keys: '.implode(', ', $unknown).'.');
        }

        if (array_key_exists('version', $definition) && $definition['version'] !== 1) {
            throw new InvalidArgumentException('Unsupported query recipe version.');
        }

        if (array_key_exists('dataset', $definition) && ! is_string($definition['dataset'])) {
            throw new InvalidArgumentException('Query recipe dataset must be a string.');
        }

        if (array_key_exists('template', $definition) && ! is_array($definition['template'])) {
            throw new InvalidArgumentException('Query recipe template must be an array.');
        }

        self::validateDefinitionList($definition, 'slots', self::SLOT_KEYS);
        self::validateDefinitionList($definition, 'filter_templates', self::FILTER_TEMPLATE_KEYS);

        foreach ($definition['slots'] ?? [] as $slot) {
            foreach (['name', 'type'] as $key) {
                if (array_key_exists($key, $slot) && ! is_string($slot[$key])) {
                    throw new InvalidArgumentException(sprintf('Query recipe slot %s must be a string.', $key));
                }
            }

            if (array_key_exists('target', $slot) && $slot['target'] !== null && ! is_string($slot['target'])) {
                throw new InvalidArgumentException('Query recipe slot target must be a string or null.');
            }

            if (array_key_exists('required', $slot) && ! is_bool($slot['required'])) {
                throw new InvalidArgumentException('Query recipe slot required must be a boolean.');
            }
        }

        foreach ($definition['filter_templates'] ?? [] as $filter) {
            foreach (self::FILTER_TEMPLATE_KEYS as $key) {
                if (array_key_exists($key, $filter) && ! is_string($filter[$key])) {
                    throw new InvalidArgumentException(sprintf('Query recipe filter template %s must be a string.', $key));
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  list<string>  $keys
     */
    private static function validateDefinitionList(array $definition, string $name, array $keys): void
    {
        if (! array_key_exists($name, $definition)) {
            return;
        }

        $items = $definition[$name];
        if (! is_array($items) || ! array_is_list($items)) {
            throw new InvalidArgumentException(sprintf('Query recipe %s must be a list of arrays.', $name));
        }

        foreach ($items as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new InvalidArgumentException(sprintf('Each query recipe %s item must be an object.', $name));
            }

            $unknown = array_diff(array_keys($item), $keys);
            if ($unknown !== []) {
                throw new InvalidArgumentException(sprintf('Unknown query recipe %s keys: %s.', $name, implode(', ', $unknown)));
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $slots
     */
    private static function availableLimitSlotName(array $slots): string
    {
        $names = array_column($slots, 'name');

        if (! in_array('limit', $names, true)) {
            return 'limit';
        }

        $suffix = 1;

        do {
            $name = $suffix === 1 ? 'result_limit' : sprintf('result_limit_%d', $suffix);
            $suffix++;
        } while (in_array($name, $names, true));

        return $name;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function validate(array $definition): void
    {
        $dataset = (string) ($definition['dataset'] ?? '');
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $dataset) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid query recipe dataset [%s].', $dataset));
        }

        $slots = (array) $definition['slots'];
        $names = [];
        $targets = [];

        foreach ($slots as $slot) {
            $name = (string) $slot['name'];
            if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid query recipe slot name [%s].', $name));
            }

            if (! in_array((string) $slot['type'], self::SLOT_TYPES, true)) {
                throw new InvalidArgumentException(sprintf('Unsupported query recipe slot type [%s].', $slot['type']));
            }

            if (
                $slot['type'] === 'timezone_offset'
                && (($slot['minimum'] ?? -840) < -840 || ($slot['maximum'] ?? 840) > 840)
            ) {
                throw new InvalidArgumentException(sprintf('Query recipe slot [%s] timezone bounds must stay between -840 and 840.', $name));
            }

            if (($slot['target'] ?? null) !== null) {
                if (! in_array((string) $slot['target'], self::SLOT_TARGETS, true)) {
                    throw new InvalidArgumentException(sprintf('Unsupported query recipe slot target [%s].', $slot['target']));
                }

                if (! in_array((string) $slot['type'], self::SLOT_TARGET_TYPES[(string) $slot['target']], true)) {
                    throw new InvalidArgumentException(sprintf(
                        'Query recipe slot [%s] type [%s] is incompatible with target [%s].',
                        $name,
                        $slot['type'],
                        $slot['target'],
                    ));
                }

                if (in_array($slot['target'], $targets, true)) {
                    throw new InvalidArgumentException(sprintf('Duplicate query recipe slot target [%s].', $slot['target']));
                }

                $targets[] = $slot['target'];
            }

            if (in_array($name, $names, true)) {
                throw new InvalidArgumentException(sprintf('Duplicate query recipe slot [%s].', $name));
            }

            $names[] = $name;

            if (isset($slot['minimum'], $slot['maximum']) && $slot['minimum'] > $slot['maximum']) {
                throw new InvalidArgumentException(sprintf('Query recipe slot [%s] minimum cannot exceed its maximum.', $name));
            }

            if (array_key_exists('default', $slot)) {
                self::slotValue($slot, $slot['default']);
            }
        }

        foreach ((array) $definition['filter_templates'] as $filter) {
            $field = (string) $filter['field'];
            $slot = (string) $filter['slot'];
            if ($field === '') {
                throw new InvalidArgumentException('Query recipe filter field is required.');
            }

            if (! in_array((string) $filter['operator'], self::FILTER_OPERATORS, true)) {
                throw new InvalidArgumentException(sprintf('Unsupported query recipe filter operator [%s].', $filter['operator']));
            }

            if (! in_array($slot, $names, true)) {
                throw new InvalidArgumentException(sprintf('Query recipe filter references unknown slot [%s].', $slot));
            }
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
            'bucket_size' => 1,
            'precision' => 5,
            'percents' => '50',
            default => throw new InvalidArgumentException(sprintf('Unsupported query recipe slot target [%s].', $target)),
        };
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    private static function slotValue(array $slot, mixed $value): int|float|string
    {
        $type = (string) $slot['type'];

        if (in_array($type, ['integer', 'timezone_offset'], true)) {
            $value = self::slotInteger($value, (string) $slot['name']);
            $minimum = max((int) ($slot['minimum'] ?? PHP_INT_MIN), $type === 'timezone_offset' ? -840 : PHP_INT_MIN);
            $maximum = min((int) ($slot['maximum'] ?? PHP_INT_MAX), $type === 'timezone_offset' ? 840 : PHP_INT_MAX);
            if (! ($value >= $minimum && $value <= $maximum)) {
                throw new InvalidArgumentException(sprintf('Query recipe binding [%s] is outside its allowed range.', $slot['name']));
            }

            return $value;
        }

        if ($type === 'number') {
            $value = self::slotNumber($value, (string) $slot['name']);
            $minimum = (float) ($slot['minimum'] ?? -PHP_FLOAT_MAX);
            $maximum = (float) ($slot['maximum'] ?? PHP_FLOAT_MAX);

            if (! ($value >= $minimum && $value <= $maximum)) {
                throw new InvalidArgumentException(sprintf('Query recipe binding [%s] is outside its allowed range.', $slot['name']));
            }

            return $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Query recipe binding [%s] must be a string.', $slot['name']));
        }

        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException(sprintf('Query recipe binding [%s] cannot be empty.', $slot['name']));
        }

        if ($type === 'period' && ! Period::tryFrom($value) instanceof Period) {
            throw new InvalidArgumentException(sprintf('Query recipe binding [%s] must be a supported period.', $slot['name']));
        }

        if ($type === 'date') {
            self::validateSlotDate($value, (string) $slot['name']);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    private static function slotBound(mixed $value, array $slot, string $bound): int|float
    {
        $type = (string) $slot['type'];

        return match ($type) {
            'integer', 'timezone_offset' => self::slotInteger($value, sprintf('%s %s', $slot['name'], $bound)),
            'number' => self::slotNumber($value, sprintf('%s %s', $slot['name'], $bound)),
            default => throw new InvalidArgumentException(sprintf('Query recipe slot [%s] may define bounds only for integer or number types.', $slot['name'])),
        };
    }

    private static function slotInteger(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Query recipe binding [%s] must be an integer.', $name));
        }

        $value = trim($value);
        if (preg_match('/^-?(0|[1-9]\d*)$/D', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Query recipe binding [%s] must be an integer.', $name));
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer !== false
            ? $integer
            : throw new InvalidArgumentException(sprintf('Query recipe binding [%s] must be an integer.', $name));
    }

    private static function slotNumber(mixed $value, string $name): float
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Query recipe binding [%s] must be numeric.', $name));
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Query recipe binding [%s] must be numeric.', $name));
        }

        $number = (float) $value;

        return is_finite($number)
            ? $number
            : throw new InvalidArgumentException(sprintf('Query recipe binding [%s] must be finite.', $name));
    }

    private static function validateSlotDate(string $value, string $name): void
    {
        $format = match (true) {
            preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1 => '!Y-m-d',
            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value) === 1 => '!Y-m-d\TH:i:sP',
            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{1,6}(?:Z|[+-]\d{2}:\d{2})$/D', $value) === 1 => '!Y-m-d\TH:i:s.uP',
            default => null,
        };

        $date = $format !== null ? DateTimeImmutable::createFromFormat($format, $value) : false;

        if ($date instanceof DateTimeImmutable && DateTimeImmutable::getLastErrors() === false) {
            return;
        }

        throw new InvalidArgumentException(sprintf('Query recipe binding [%s] must be a valid ISO 8601 date.', $name));
    }

    /**
     * @param  list<array<string, string>>  $templates
     * @param  array<string, int|float|string>  $bindings
     */
    private function boundFilters(array $templates, array $bindings): string
    {
        $filters = [];

        foreach ($templates as $filter) {
            $slot = (string) $filter['slot'];

            if (! array_key_exists($slot, $bindings)) {
                continue;
            }

            $filters[] = $this->filterClause((string) $filter['field'], (string) $filter['operator'], $bindings[$slot]);
        }

        return implode(' AND ', $filters);
    }

    private function filterClause(string $field, string $operator, int|float|string $value): string
    {
        $value = is_int($value) || is_float($value)
            ? (string) $value
            : "'".str_replace(['\\', "'", '*'], ['\\\\', "\\'", '\\*'], $value)."'";

        return match ($operator) {
            'equals' => sprintf('%s:%s', $field, $value),
            'not_equals' => sprintf('NOT %s:%s', $field, $value),
            'gt' => sprintf('%s>%s', $field, $value),
            'gte' => sprintf('%s>=%s', $field, $value),
            'lt' => sprintf('%s<%s', $field, $value),
            'lte' => sprintf('%s<=%s', $field, $value),
            default => throw new InvalidArgumentException(sprintf('Unsupported query recipe filter operator [%s].', $operator)),
        };
    }

    /** @param array<string, mixed> $slot */
    private function validateFilterSlotType(string $fieldType, string $operator, array $slot, string $field): void
    {
        $slotType = (string) ($slot['type'] ?? '');
        $valid = match ($fieldType) {
            'number' => in_array($slotType, ['integer', 'number'], true),
            'date' => $slotType === 'date',
            'keyword', 'text' => $slotType === 'string' && in_array($operator, ['equals', 'not_equals'], true),
            default => false,
        };

        if (! $valid) {
            throw new InvalidArgumentException(sprintf('Query recipe filter slot for [%s] does not match its field type or operator.', $field));
        }
    }

    private function validateStaticFilter(NewProperties $properties, string $filter, string $argument): void
    {
        try {
            (new FilterParser($properties))->parse($filter);
        } catch (ParseException $parseException) {
            throw new InvalidArgumentException(
                sprintf('Invalid query recipe %s: %s', $argument, $parseException->getMessage()),
                0,
                $parseException,
            );
        }
    }

    private function validateSort(NewProperties $properties, string $sort, string $argument, bool $multiple): void
    {
        $tokens = preg_split('/\s+/', trim($sort)) ?: [];

        if (! $multiple && count($tokens) !== 1) {
            throw new InvalidArgumentException('Query recipe sort must contain exactly one field.');
        }

        foreach ($tokens as $token) {
            if (in_array($token, ['_score', '_doc', '_score:desc'], true)) {
                continue;
            }

            if ($multiple && preg_match('/^[\w.]+\[-?\d+(?:\.\d+)?,-?\d+(?:\.\d+)?\]:\w+:\w+$/D', $token) === 1) {
                continue;
            }

            [$field, $direction] = array_pad(explode(':', $token, 3), 2, null);

            if ($direction !== null && ! in_array($direction, ['asc', 'desc'], true) && substr_count($token, ':') === 1) {
                throw new InvalidArgumentException(sprintf('Unsupported query recipe %s direction [%s].', $argument, $direction));
            }

            if ($field === '' || substr_count($token, ':') > 1) {
                throw new InvalidArgumentException(sprintf('Unsupported query recipe %s [%s].', $argument, $token));
            }
        }

        try {
            (new SortParser($properties))->parse($sort);
        } catch (ParseException $parseException) {
            throw new InvalidArgumentException(
                sprintf('Invalid query recipe %s: %s', $argument, $parseException->getMessage()),
                0,
                $parseException,
            );
        }
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
            $name = $prefix !== '' ? sprintf('%s.%s', $prefix, $field->name) : $field->name;
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
    private function requireField(array $fields, string $field, string $argument): void
    {
        if (! isset($fields[$field])) {
            throw new InvalidArgumentException(sprintf('Query recipe %s field [%s] does not exist in the index.', $argument, $field));
        }
    }

    /**
     * @param  array<string, string>  $fields
     * @param  list<string>  $types
     */
    private function requireFieldType(array $fields, string $field, array $types, string $argument): void
    {
        $this->requireField($fields, $field, $argument);
        if (! in_array($fields[$field], $types, true)) {
            throw new InvalidArgumentException(sprintf('Query recipe %s field [%s] has incompatible type [%s].', $argument, $field, $fields[$field]));
        }
    }

    /**
     * @param  array<string, string>  $fields
     * @param  list<string>  $groupByFields
     */
    private function validateUnionGroupFields(array $fields, array $groupByFields): void
    {
        $types = array_values(array_unique(array_map(
            fn (string $field): string => in_array($fields[$field], ['keyword', 'text'], true)
                ? 'string'
                : $fields[$field],
            $groupByFields,
        )));

        if (count($types) > 1) {
            throw new InvalidArgumentException('Query recipe union_breakdown group_by_fields must have compatible types.');
        }
    }

    /** @return list<string> */
    private function csvFields(string $fields): array
    {
        return array_values(array_filter(array_map(
            fn (string $field): string => trim($field),
            explode(',', $fields),
        )));
    }
}
