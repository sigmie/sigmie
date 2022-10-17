<?php

declare(strict_types=1);

namespace Sigmie\Filter;

use Sigmie\Base\Mappings\ElasticsearchMappingType;
use Sigmie\Base\Search\Search;

class SortParser
{
    protected array $sortMatches = [];

    protected array $errors = [];

    public function __construct(protected string $queryString, protected array $rawProperties)
    {
        $this->sortMatches = explode(' ', $this->queryString);
    }

    public function __invoke(): array
    {
        $sort = [];
        foreach ($this->sortMatches as $match) {

            if (in_array($match, ['_score', '_doc'])) {
                $sort[] = $match;
                continue;
            }

            if (str_contains($match, ':')) {
                [$field, $direction] = explode(':', $match);
            } else {
                $field = $match;
                $direction = 'asc';
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

            if ($this->isTextField($field)) {
                $field = $this->textFieldKeywordName($field);

                $sort[] = [$field => $direction];
                continue;
            }

            $sort[] = [$field => $direction];
        }

        return $sort;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function textFieldKeywordName(string $field): string
    {
        $fields = $this->rawProperties;

        $keywordName = array_key_first($fields[$field]['fields']);

        return "{$field}.{$keywordName}";
    }

    private function isTextField(string $field): bool
    {
        $fields = $this->rawProperties;

        $field = $fields[$field];

        return $field['type'] === 'text';
    }

    private function isSortableField(string $field)
    {
        $fields = $this->rawProperties;

        //Field doesn't exist
        if (!in_array($field, array_keys($fields))) {
            return false;
        }

        $field = $fields[$field];
        $type = $field['type'];

        if (
            $type === ElasticsearchMappingType::INTEGER->value ||
            $type === ElasticsearchMappingType::FLOAT->value ||
            $type === ElasticsearchMappingType::LONG->value ||
            $type === ElasticsearchMappingType::DATE->value ||
            $type === ElasticsearchMappingType::KEYWORD->value ||
            (($type === ElasticsearchMappingType::TEXT->value ||
                $type === ElasticsearchMappingType::SEARCH_AS_YOU_TYPE->value ||
                $type === ElasticsearchMappingType::COMPLETION->value) &&
                $field['fields']['keyword']['type'] ?? false === 'keyword'
            )
        ) {
            return true;
        }

        return false;
    }

    private function fieldExists(string $field): bool
    {
        $fields = $this->rawProperties;

        //Field doesn't exist
        if (!in_array($field, array_keys($fields))) {
            return false;
        }

        return true;
    }
}
