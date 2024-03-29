<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Field;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
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

            $params = '10';

            if (str_contains($field, ':')) {
                [$field, $params] = explode(':', $field);
            }

            if (!$this->fieldExists($field)) {
                $this->handleError("Field {$field} does not exist.", [
                    'field' => $field,
                ]);

                continue;
            }

            /** @var Type $field  */
            $field = $this->properties[$field];

            if (!$field->isFacetable()) {
                $this->handleError("The field '{$field->name()}' does not support facets.", [
                    'field' => $field->name(),
                ]);
                continue;
            }

            try {
                $field->aggregation($aggregation, $params);
            } catch (ParseException $e) {
                $this->handleError($e->getMessage(), [
                    'field' => $field->name(),
                ]);
            }
        }

        return $aggregation;
    }
}
