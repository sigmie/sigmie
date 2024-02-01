<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Field;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Text;
use Sigmie\Query\Aggs;

class FacetParser extends Parser
{
    public function parse(string $string): Aggs
    {
        // Remove extra spaces that aren't in quotes
        // and replace them with only one. This regex handles
        // also quotes that are escapted
        $string = preg_replace('/\s+(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', ' ', $string);

        $string = trim($string);

        $string = str_replace(["\r", "\n"], ' ', $string);

        $facets = explode(' ', $string);

        $aggregation = new Aggs;

        foreach ($facets as $field) {

            $param = 10;

            if (str_contains($field, ':')) {
                [$field, $param] = explode(':', $field);
            }

            if (!is_numeric($param)) {
                $this->handleError("Limit {$param} must be numeric.", [
                    'field' => $field,
                ]);
            }

            $param = (int) $param;

            if (!$this->fieldExists($field)) {
                $this->handleError("Field {$field} does not exist.", [
                    'field' => $field,
                ]);

                continue;
            }

            $field = $this->properties[$field];

            if ($field instanceof Price) {
                $aggregation->histogram($field->name(), $field->name(), interval: $param);

                continue;
            }

            if ($field instanceof Text && $field->isFilterable()) {
                $aggregation->terms($field->name(), $field->filterableName())->size($param);

                continue;
            }

            if ($field instanceof Keyword) {
                $aggregation->terms($field->name(), $field->name())->size($param);

                continue;
            }

            if ($field instanceof Number || $field instanceof Price) {
                $aggregation->stats($field->name(), $field->name());

                continue;
            }

            if ($field instanceof Boolean) {
                $aggregation->terms($field->name(), $field->name());

                continue;
            }

            $this->handleError("Field {$field->name()} is not filterable.", [
                'field' => $field->name(),
            ]);
        }

        return $aggregation;
    }
}
