<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Base\Mappings\ElasticsearchMappingType;
use Sigmie\Search\Search;

class SortParser extends Parser
{
    public function parse(string $string): array
    {
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

            $field = $this->handleFieldName($field);

            $sort[] = [$field => $direction];
        }

        return $sort;
    }
}
