<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Types\Type;
use Sigmie\Query\Aggs;

class FacetParser extends Parser
{
    public function parse(string $string): Aggs
    {
        $this->errors = [];

        // Remove extra spaces that aren't in quotes
        // and replace them with only one. This regex handles
        // also quotes that are escapted
        $string = preg_replace('/\s+(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', ' ', $string);

        $string = trim($string);

        $string = str_replace(["\r", "\n"], ' ', $string);

        $facets = explode(' ', $string);

        $aggregation = new Aggs;

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

            try {
                if ($field->parentPath) {
                    $aggregation->nested($field->name(), $field->parentPath, function (Aggs $aggs) use ($params, $field) {
                        $field->aggregation($aggs, $params);
                    });
                } else {
                    $field->aggregation($aggregation, $params);
                }
            } catch (ParseException $e) {
                $this->handleError($e->getMessage(), [
                    'field' => $field->name(),
                ]);
            }
        }

        return $aggregation;
    }
}
