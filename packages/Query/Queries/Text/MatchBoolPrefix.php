<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Queries\Query;

class MatchBoolPrefix extends Query
{
    public function __construct(
        protected string $field,
        protected string $query,
        protected string|null $fuzziness = null,
    ) {
    }

    public function toRaw(): array
    {
        $raw = [
            'match_bool_prefix' => [
                $this->field => [
                    'query' => $this->query,
                    'boost' => $this->boost,
                ],
            ],
        ];

        if (is_null($this->fuzziness)) {
            return $raw;
        }

        $raw['match'][$this->field]['fuzziness'] = $this->fuzziness;
        $raw['match'][$this->field]['fuzzy_transpositions'] = true;
        $raw['match'][$this->field]['prefix_length'] = 0;

        return $raw;
    }
}
