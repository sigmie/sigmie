<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Type;
use Sigmie\Query\Aggs;

class FacetParser extends Parser
{
    public function parseFilterString(string $filterString): string
    {
        // Trim leading and trailing spaces
        $filterString = trim($filterString);

        if ($filterString === '') {
            return '';
        }

        // Remove AND NOT, AND and OR from the filter string
        if (preg_match('/\b(?:AND NOT|AND|OR)\b(?=(?:(?:[^\'"\{\}]*[\'"\{\}]){2})*[^\'"\{\}]*$)(?=(?:(?:[^\{\}]*\{[^\{\}]*\})*[^\{\}]*$))/', $filterString)) {
            $this->handleError("Facet filter string cannot contain logical operators (AND, OR, AND NOT): '{$filterString}'");
        }

        if (preg_match('/\((?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $filterString)) {
            $this->handleError("Facet filter string cannot contain parenthetic expressions: '{$filterString}'");
        }

        if (count($this->errors()) > 0) {
            return '';
        }

        $filters = $this->explode($filterString);

        $fields = [];

        foreach ($filters as $filter) {

            // Match field name
            if (preg_match('/^([\w\.]+)[:<=](.*)$/', $filter, $matches)) {
                $field = $matches[1];
            } else {
                $field = $filter;
            }

            if (!isset($fields[$field])) {
                $fields[$field] = [];
            }

            $fields[$field][] = $filter;
        }

        $res = [];
        foreach ($fields as $field => $values) {

            $type = $this->properties->getNestedField($field);

            $fieldFilters = [];

            foreach ($values as $value) {
                $fieldFilters[] = $value;
            }

            if (is_null($type)) {
                $this->handleError("Facet field '{$field}' was not found.", [
                    'field' => $field,
                ]);

                continue;
            }

            if ($type->isFacetable() && $type->isFacetConjunctive()) {
                $facetFilter = implode(' AND ', $fieldFilters);
            }

            if ($type->isFacetable() && $type->isFacetDisjunctive()) {
                $facetFilter = implode(' OR ', $fieldFilters);
            }

            $res[] = "({$facetFilter})";
        }

        return implode(' AND ', $res);
    }

    public function parse(string $string, string $filterString = ''): Aggs
    {
        $facets = $this->explode($string);

        $aggregation = new Aggs;
        $filterParser = new FilterParser($this->properties, $this->throwOnError);

        foreach ($facets as $field) {

            $params = '10';

            if (str_contains($field, ':')) {
                [$field, $params] = explode(':', $field);
            }

            if (! $this->fieldExists($field)) {
                $this->handleError("Field {$field} does not exist.", [
                    'field' => $field,
                ]);

                continue;
            }


            /** @var Type $field */
            $field = $this->properties->getNestedField($field);


            if (! $field->isFacetable()) {
                $this->handleError("The field '{$field->name()}' does not support facets.", [
                    'field' => $field->name(),
                ]);

                continue;
            }

            $query = $filterParser->facetFilter($field, $filterString);

            try {
                if ($field->parentPath) {

                    $aggregation->nested($field->name(), $field->parentPath, function (Aggs $aggs) use ($params, $field, $query) {

                        $aggs->filter($field->name(), $query)
                            ->aggregate(function (Aggs $aggs) use ($params, $field) {
                                $field->aggregation($aggs, $params);
                            });

                        // $field->aggregation($aggs, $params);
                    });
                } else {

                    $aggregation->filter($field->name(), $query)
                        ->aggregate(function (Aggs $aggs) use ($params, $field) {
                            $field->aggregation($aggs, $params);
                        });

                    // dd($aggregation->toRaw());
                    // $field->aggregation($aggregation, $params);
                }
            } catch (ParseException $e) {
                $this->handleError($e->getMessage(), [
                    'field' => $field->name(),
                ]);
            }
        }

        return $aggregation;
    }

    protected function explode(string $string)
    {

        $string = preg_replace('/\s+(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', ' ', $string);

        $string = trim($string);

        $string = str_replace(["\r", "\n"], ' ', $string);

        return explode(' ', $string);
    }

    public function fields(string $string): array
    {
        $fields = $this->explode($string);

        return array_map(function ($field) {

            if (str_contains($field, ':')) {
                [$field, $param] = explode(':', $field);
            }

            return $field;
        }, $fields);
    }
}
