<?php

declare(strict_types=1);

namespace Sigmie\Filter;

use Sigmie\Base\Mappings\Properties;
use Sigmie\Base\Mappings\Types\Date;
use Sigmie\Base\Mappings\Types\Keyword;
use Sigmie\Base\Mappings\Types\Number;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Base\Search\Search;

class SortParser
{
    protected array $sortMatches = [];

    protected array $errors = [];

    public function __construct(protected string $queryString, protected Properties $properties)
    {
        $sortPattern = '/sort:\w+(-(asc|desc))?/';
        preg_match_all($sortPattern, $queryString, $this->sortMatches);
        $queryString = preg_replace($sortPattern, '', $queryString);
    }

    public function __invoke(Search $search): void
    {
        foreach ($this->sortMatches[0] as $match) {
            $direction = 'asc';
            [, $field] = explode(':', $match);

            $fieldWithDirection = explode('-', $field);

            if (count($fieldWithDirection) > 1) {
                [$field, $direction] = $fieldWithDirection;
            }

            if (!$this->fieldExists($field)) {
                $this->errors[] = [
                    'match' => $match,
                    'message' => "Field {$field} is does not exist.",
                    'field' => $field,
                ];
                continue;
            }

            if (!$this->isSortableField($field)) {
                $this->errors[] = [
                    'match' => $match,
                    'message' => "Field {$field} is not sortable.",
                    'field' => $field,
                ];
                continue;
            }

            if (!in_array($direction, ['asc', 'desc'])) {
                $this->errors[] = [
                    'match' => $match,
                    'message' => "{$direction} is not a valid sort direction.",
                    'field' => $field,
                ];
                continue;
            }

            if ($this->isTextField($field)) {
                $field = $this->textFieldKeywordName($field);

                $search->sort($field, $direction);
                continue;
            }

            $search->sort($field, $direction);
        }
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function textFieldKeywordName(string $field): string
    {
        $fields = $this->properties->toArray();
        return $fields[$field]->sortableName();
    }

    private function isTextField(string $field): bool
    {
        $fields = $this->properties->toArray();
        $field = $fields[$field];

        return $field instanceof Text;
    }

    private function isSortableField(string $field)
    {
        $fields = $this->properties->toArray();

        //Field doesn't exist
        if (!in_array($field, array_keys($fields))) {
            return false;
        }

        $field = $fields[$field];

        if ($field instanceof Number) {
            return true;
        }

        if ($field instanceof Text && $field->isSortable()) {
            return true;
        }

        if ($field instanceof Keyword) {
            return true;
        }

        if ($field instanceof Date) {
            return true;
        }

        return false;
    }

    private function fieldExists(string $field): bool
    {
        $fields = $this->properties->toArray();

        //Field doesn't exist
        if (!in_array($field, array_keys($fields))) {
            return false;
        }

        return true;
    }
}
