<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Types\Nested as NestedField;
use Sigmie\Mappings\Types\Type;
use Sigmie\Query\Aggregations\Bucket\Filter;
use Sigmie\Query\Aggregations\Bucket\Nested;
use Sigmie\Query\Aggs;

class FacetParser extends Parser
{
    public function parseFilterString(string $filterString): string
    {
        $this->errors = [];

        // Trim leading and trailing spaces
        $filterString = trim($filterString);

        if ($filterString === '') {
            return '';
        }

        $quoted = '(?:'.self::QUOTED_VALUE.')(*SKIP)(*F)';
        $filters = $this->filterClauses(preg_replace('~'.$quoted.'|\s*(:|[<>]=?)\s*~su', '$1', $filterString) ?? $filterString);
        foreach ($filters as $filter) {
            if (in_array(strtoupper($filter), ['AND', 'OR'], true)) {
                $this->handleError(sprintf("Facet filter string cannot contain logical operators (AND, OR, AND NOT): '%s'", $filterString));
                break;
            }
        }

        if (preg_match('~'.$quoted.'|[()]~s', $filterString)) {
            $this->handleError(sprintf("Facet filter string cannot contain parenthetic expressions: '%s'", $filterString));
        }

        if ($this->errors() !== []) {
            return '';
        }

        $fields = [];

        foreach ($filters as $filter) {

            $field = $this->selectionField($filter);

            if (! isset($fields[$field])) {
                $fields[$field] = [];
            }

            $fields[$field][] = $filter;
        }

        $res = [];
        $parser = new FilterParser($this->properties, throwOnError: false);
        foreach ($fields as $field => $values) {
            $field = (string) $field;

            $type = $this->properties->get($field);

            if (! $type instanceof Type && $field !== '_id') {
                $this->handleError(sprintf("Facet field '%s' was not found.", $field), [
                    'field' => $field,
                ]);

                continue;
            }

            // Drop invalid selections before grouping so they cannot narrow sibling facets.
            $fieldFilters = [];
            foreach ($values as $value) {
                if (! $this->throwOnError) {
                    $parser->parse($value);
                    if ($parser->errors() !== []) {
                        array_push($this->errors, ...$parser->errors());

                        continue;
                    }
                }

                $fieldFilters[] = $value;
            }

            if ($fieldFilters === []) {
                continue;
            }

            // Searchable and non-facetable fields are neither conjunctive nor disjunctive.
            // Group them once anyway, or the next field repeats the previous group.
            $disjunctive = $type?->isFacetable() && $type->isFacetDisjunctive();

            // A negated selection excludes its value, so it narrows a disjunctive facet too.
            $negated = array_filter(
                $fieldFilters,
                fn (string $filter): bool => preg_match('/^(?:NOT\s+)+/i', $filter, $nots) === 1
                    && substr_count(strtoupper($nots[0]), 'NOT') % 2 === 1,
            );
            $selected = array_diff_key($fieldFilters, $negated);

            $facetFilter = match (true) {
                ! $disjunctive => implode(' AND ', $fieldFilters),
                $negated === [] => implode(' OR ', $selected),
                $selected === [] => implode(' AND ', $negated),
                default => implode(' AND ', ['('.implode(' OR ', $selected).')', ...$negated]),
            };

            $res[] = sprintf('(%s)', $facetFilter);
        }

        return implode(' AND ', $res);
    }

    private function selectionField(string $clause): string
    {
        $path = '';
        $properties = $this->properties;
        do {
            $clause = preg_replace('/^(?:NOT\s+)+/i', '', $clause);
            if (! preg_match('/^('.self::FIELD_PATH.')(?:<=|>=|<|>|:)(.*)$/su', $clause, $match)) {
                return $clause;
            }

            $field = $properties->get($match[1]);
            $path = $field?->fullPath() ?? ($path === '' ? $match[1] : $path.'.'.$match[1]);
            if (! $field instanceof NestedField || ! str_starts_with($match[2], '{') || ! str_ends_with($match[2], '}')) {
                break;
            }

            // Several predicates inside one nested selection must stay in the same item.
            $children = $this->filterClauses(trim(substr($match[2], 1, -1)));
            $clause = $children[0] ?? '';
            $properties = $field->getProperties();
        } while (count($children) === 1);

        return $path;
    }

    public function parse(string $string, string $filterString = ''): Aggs
    {
        $this->errors = [];
        $this->fields = [];

        $facets = $this->explode($string);

        $aggregation = new Aggs;
        $filterParser = new FilterParser($this->properties, $this->throwOnError);

        foreach ($facets as $field) {

            $params = '10';

            if (str_contains($field, ':')) {
                [$field, $params] = explode(':', $field, 2);
            }

            if (! $this->fieldExists($field)) {
                $this->handleError(sprintf('Field %s does not exist.', $field), [
                    'field' => $field,
                ]);

                continue;
            }

            /** @var Type $field */
            $field = $this->properties->get($field);

            if (! $field->isFacetable()) {
                $this->handleError(sprintf("The field '%s' does not support facets.", $field->name()), [
                    'field' => $field->name(),
                ]);

                continue;
            }

            try {
                $name = $field->name();
                $nestedPath = $field->nestedPath();

                if ($nestedPath === null) {
                    $filter = new Filter($name, $filterParser->facetFilter($field, $filterString));
                    $filter->aggregate(fn (Aggs $aggs) => $field->aggregation($aggs, $params));
                    $aggregation->add($filter);
                    $this->fields[$name] = $name;

                    continue;
                }

                $queries = $filterParser->nestedFacetFilters($field, $filterString, $nestedPath);
                $outerScope = (string) array_key_first($queries);
                $filter = new Filter($name, $queries[$nestedPath]);
                $filter->aggregate(fn (Aggs $aggs) => $field->aggregation($aggs, $params));
                $bucket = new Nested($nestedPath === $outerScope ? $name : '_nested', $nestedPath, (new Aggs)->add($filter));
                unset($queries[$nestedPath]);

                // Apply each predicate where that field lives before entering its children.
                foreach (array_reverse($queries, preserve_keys: true) as $scope => $query) {
                    $scope = (string) $scope;
                    $filter = new Filter($scope === '' ? $name : '_nested', $query);
                    $filter->aggregate(fn (Aggs $aggs): Aggs => $aggs->add($bucket));
                    $bucket = $scope === ''
                        ? $filter
                        : new Nested($scope === $outerScope ? $name : '_nested', $scope, (new Aggs)->add($filter));
                }

                $aggregation->add($bucket);
                $this->fields[$name] = $name;
            } catch (ParseException $e) {
                $this->handleError($e->getMessage(), [
                    'field' => $field->name(),
                ]);
            }
        }

        return $aggregation;
    }

    /** @return list<string> */
    public function parsedFields(): array
    {
        return array_values($this->fields);
    }

    protected function explode(string $string): array
    {
        $parts = [];
        $current = '';
        $quote = null;
        $depth = 0;
        $characters = mb_str_split($string, 1, 'UTF-8');
        $length = count($characters);

        for ($i = 0; $i < $length; $i++) {
            $char = $characters[$i];

            if ($quote !== null) {
                $current .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $characters[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $current .= $char;

                continue;
            }

            if ($char === '{' || $char === '[') {
                $depth++;
                $current .= $char;

                continue;
            }

            if ($char === '}' || $char === ']') {
                $depth = max(0, $depth - 1);
                $current .= $char;

                continue;
            }

            if ($depth === 0 && preg_match('/^\s$/u', $char)) {
                if ($current !== '') {
                    $parts[] = $current;
                    $current = '';
                }

                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    private function filterClauses(string $string): array
    {
        $clauses = [];

        foreach ($this->explode($string) as $part) {
            $previous = array_key_last($clauses);

            if ($previous !== null && preg_match('/^(?:NOT )*NOT$/i', $clauses[$previous]) === 1) {
                $clauses[$previous] .= ' '.$part;

                continue;
            }

            $clauses[] = $part;
        }

        return $clauses;
    }

    public function fields(string $string): array
    {
        $fields = $this->explode($string);

        return array_map(static fn (string $field): string => explode(':', $field, 2)[0], $fields);
    }
}
