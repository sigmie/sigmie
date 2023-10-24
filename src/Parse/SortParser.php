<?php

declare(strict_types=1);

namespace Sigmie\Parse;

class SortParser extends Parser
{
    public function parse(string $string): array
    {
        $string = trim($string);

        if ($string === '') {
            return ['_score'];
        }

        $sorts = explode(' ', $string);
        $sort = [];

        foreach ($sorts as $match) {
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

            $field = $this->handleSortableFieldName($field);

            // Field isn't sortable
            if (is_null($field)) {
                continue;
            }

            $sort[] = [$field => $direction];
        }

        return $sort;
    }
}
