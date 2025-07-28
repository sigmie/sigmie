<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Type;
use Sigmie\Query\Aggs;

class FacetParser extends Parser
{
    public function parse(string $string, string $filterString = ''): Aggs
    {
        $this->errors = [];

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

                    $aggregation->nested($field->name(), $field->parentPath, function (Aggs $aggs) use ($params, $field) {
                        $field->aggregation($aggs, $params);
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
