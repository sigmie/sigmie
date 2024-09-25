<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\Queries\Query;

class MatchBoolPrefix extends Query implements FuzzyQuery
{
    public function __construct(
        protected string $field,
        protected string $query,
        protected ?string $fuzziness = null,
    ) {}

    public function fuzziness(?string $fuzziness): static
    {
        $this->fuzziness = $fuzziness;

        return $this;
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

        $raw['match_bool_prefix'][$this->field]['fuzziness'] = $this->fuzziness;
        $raw['match_bool_prefix'][$this->field]['fuzzy_transpositions'] = true;
        $raw['match_bool_prefix'][$this->field]['prefix_length'] = 0;

        return $raw;
    }
}
