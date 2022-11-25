<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\Queries\Query;

class Match_ extends Query implements FuzzyQuery
{
    public function __construct(
        protected string $field,
        protected string $query,
        protected string|null $fuzziness = null,
    ) {
    }

    public function fuzziness(null|string $fuzziness): static
    {
        $this->fuzziness = $fuzziness;

        return $this;
    }

    public function toRaw(): array
    {
        $raw = [
            'match' => [
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
