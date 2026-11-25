<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use function Sigmie\Functions\keyed;

class MatchPhrase extends MatchQuery
{
    public function __construct(
        protected string $field,
        string $query,
        ?string $analyzer = null,
    ) {
        parent::__construct($query, $analyzer);
    }

    public function toRaw(): array
    {
        return [
            'match_phrase' => keyed($this->field, $this->matchOptions()),
        ];
    }
}
