<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use ReflectionClass;
use Sigmie\Mappings\Contracts\Type as TypeInterface;
use Sigmie\Search\Contracts\TextQueries;
use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;

use function Sigmie\Functions\lowercase_term;
use function Sigmie\Functions\merge_named;
use function Sigmie\Functions\object_map;

abstract class Type implements Name, TextQueries, ToRaw, TypeInterface
{
    protected string $type;

    protected array $meta = [];

    protected ?array $storedMapping = null;

    protected string|array|null $copyTo = null;

    /**
     * Human/agent-facing description of the field (e.g. for AI tools). NOT sent to
     * Elasticsearch — kept out of toRaw() so it is not constrained by ES field-meta limits.
     */
    protected ?string $description = null;

    protected string $path = '';

    protected ?string $nestedPath = null;

    public bool $hasQueriesCallback = false;

    public Closure $queriesClosure;

    public function __construct(public string $name) {}

    public function setPath(string $path): static
    {
        $boundary = strrpos($path, '>');
        $fullPath = str_replace('>', '.', $path);

        return $this->setFieldPath($fullPath, $boundary === false ? null : substr($fullPath, 0, $boundary));
    }

    public function setFieldPath(string $fullPath, ?string $nestedPath = null): static
    {
        $this->path = $fullPath;
        $this->nestedPath = $nestedPath;

        return $this;
    }

    public function copyPathFrom(self $field): static
    {
        return $this->setFieldPath($field->path, $field->nestedPath);
    }

    public function fullPath(): string
    {
        return $this->path === '' ? $this->name : $this->path;
    }

    public function nestedPath(): ?string
    {
        return $this->nestedPath;
    }

    public function queries(array|string $queryString): array
    {
        return [];
    }

    /**
     * An array is one query string per element. Each element takes the string
     * path. The array|string signature does not say what the array holds.
     *
     * @param  Closure(string): list<mixed>  $one
     */
    protected function queryStrings(array|string $queryString, Closure $one): array
    {
        if (! is_array($queryString)) {
            return $one($queryString);
        }

        $queries = [];

        foreach ($queryString as $query) {
            foreach ($one($query) as $built) {
                $queries[] = $built;
            }
        }

        return $queries;
    }

    protected function lowercaseTerm(string $term): string
    {
        return lowercase_term($term);
    }

    public function queryStringQueries(array|string $queryString): array
    {
        if ($this->hasQueriesCallback) {
            return self::queryStrings($queryString, fn (string $query): array => $this->queriesFromCallback($query));
        }

        return $this->queries($queryString);
    }

    public function queriesFromCallback(string $queryString): array
    {
        return ($this->queriesClosure)($queryString);
    }

    public function withQueries(Closure $closure): static
    {
        $this->hasQueriesCallback = true;
        $this->queriesClosure = $closure;

        return $this;
    }

    public function meta(array $meta): void
    {
        $this->meta = merge_named($this->meta, $meta);
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function copyTo(string|array $fields): static
    {
        $this->copyTo = is_array($fields) ? array_values($fields) : $fields;

        return $this;
    }

    /**
     * Set an agent/AI-facing description of the field. Surfaced by SigmieIndexTool; never
     * sent to Elasticsearch (so it is free of ES field-meta length/count limits).
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isFacetable(): bool
    {
        return false;
    }

    public function __invoke(): array
    {
        return $this->toRaw();
    }

    public function type(): string
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->fullPath();
    }

    public function names(): array
    {
        return [
            $this->fullPath(),
        ];
    }

    public function restoreScalarMapping(array $mapping): void
    {
        if (isset($mapping['fields'])) {
            $mapping['fields'] = object_map((array) $mapping['fields']);
        }

        $this->storedMapping = $mapping;
        $this->meta = array_diff_key((array) ($mapping['meta'] ?? []), ['type' => true]);
    }

    public function toRaw(): array
    {
        $mapping = ['type' => $this->type()];
        if ($this->storedMapping !== null && $this->storedMapping['type'] === $this->type()) {
            $mapping += $this->storedMapping;
            $meta = array_replace((array) ($mapping['meta'] ?? []), $this->meta);
            if ($meta !== [] || array_key_exists('meta', $mapping)) {
                $mapping['meta'] = array_is_list($meta) ? (object) $meta : $meta;
            }
        } elseif (! in_array($this->type(), ['nested', 'completion', 'object'])) {
            $mapping['meta'] = merge_named($this->meta, ['type' => $this->typeName()]);
        }

        $raw = [$this->name => $mapping];
        if ($this->copyTo !== null) {
            $raw[$this->name]['copy_to'] = $this->copyTo;
        }

        return $raw;
    }

    protected function analysisComponentName(string $fieldName, string $suffix): string
    {
        // Dots are settings separators, and Elasticsearch reserves leading underscores for analyzers.
        $name = strtr($fieldName, ['%' => '%25', '.' => '%2E']);
        if (str_starts_with($name, '_')) {
            $name = '%5F'.substr($name, 1);
        }

        return $name.$suffix;
    }

    public function filterableName(): ?string
    {
        return $this->fullPath();
    }

    public function sortableName(): ?string
    {
        return match ($this->type()) {
            'object', 'nested', 'dense_vector', 'knn_vector' => null,
            default => $this->fullPath(),
        };
    }

    protected function ignoresMalformedScalar(mixed $value): bool
    {
        return ($this->storedMapping['ignore_malformed'] ?? false) === true && is_scalar($value)
            && (! is_float($value) || is_finite($value));
    }

    protected function validateKeywordTerms(string $key, string $value): array
    {
        // Even four UTF-8 bytes per character fit below this threshold after lowercasing.
        if (strlen($value) <= 8191) {
            return [true, ''];
        }

        $mapping = $this->toRaw()[$this->name];
        foreach ([$mapping, ...(array) ($mapping['fields'] ?? [])] as $name => $field) {
            $field = (array) $field;
            if ($field['type'] !== 'keyword') {
                continue;
            }

            if (! ($field['index'] ?? true) && ! ($field['doc_values'] ?? true)) {
                continue;
            }

            if (isset($field['ignore_above']) && strlen(mb_convert_encoding($value, 'UTF-16BE', 'UTF-8')) / 2 > $field['ignore_above']) {
                continue;
            }

            $normalizer = $field['normalizer'] ?? null;
            $fieldName = $name === 0 ? $this->name : (string) $name;
            if ($normalizer !== null && $normalizer !== $this->analysisComponentName($fieldName, '_field_normalizer') && $normalizer !== 'lowercase') {
                continue;
            }

            $term = $normalizer === null ? $value : $this->lowercaseTerm($value);
            if (strlen($term) > 32766) {
                return [false, sprintf('The field %s exceeds the keyword term limit of 32766 bytes', $key)];
            }
        }

        return [true, ''];
    }

    public function validate(string $key, mixed $value): array
    {
        return [true, ''];
    }

    public function typeName(): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (new ReflectionClass($this))->getShortName()));
    }
}
