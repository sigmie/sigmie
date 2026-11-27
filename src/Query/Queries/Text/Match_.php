<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Contracts\FuzzyQuery;

use function Sigmie\Functions\keyed;

class Match_ extends MatchQuery implements FuzzyQuery
{
    public function __construct(
        protected string $field,
        string $query,
        protected ?string $fuzziness = null,
        ?string $analyzer = null,
        protected ?string $operator = null,
    ) {
        parent::__construct($query, $analyzer);
    }

    public function fuzziness(?string $fuzziness): static
    {
        $this->fuzziness = $fuzziness;

        return $this;
    }

    public function toRaw(): array
    {
        $clause = $this->matchOptions();

        if ($this->operator !== null) {
            $clause['operator'] = $this->operator;
        }

        if (! is_null($this->fuzziness)) {
            $clause['fuzziness'] = $this->fuzziness;
            $clause['fuzzy_transpositions'] = true;
            $clause['prefix_length'] = 0;
        }

        return [
            'match' => keyed($this->field, $clause),
        ];
    }
}
