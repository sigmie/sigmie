<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Field;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Number;
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

            $limit = 10;

            if (str_contains($field, ':')) {
                [$field, $limit] = explode(':', $field);
            }

            if (!is_numeric($limit)) {
                $this->handleError("Limit {$limit} must be numeric.", [
                    'field' => $field,
                ]);
            }

            $limit = (int) $limit;

            if (!$this->fieldExists($field)) {
                $this->handleError("Field {$field} does not exist.", [
                    'field' => $field,
                ]);

                continue;
            }

            $field = $this->properties[$field];

            if ($field instanceof Text && $field->isFilterable()) {
                $aggregation->terms($field->name(), $field->filterableName())->size($limit);

                continue;
            }

            if ($field instanceof Keyword) {
                $aggregation->terms($field->name(), $field->name())->size($limit);

                continue;
            }

            if ($field instanceof Number) {
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
