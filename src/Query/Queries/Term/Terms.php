<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Term;

use Sigmie\Query\Queries\Query;

class Terms extends Query
{
    public function __construct(
        protected string $field,
        protected array $values
    ) {}

    public function toRaw(): array
    {
        // Explicit terms have numeric keys. A terms lookup has named parameters.
        $values = is_int(array_key_first($this->values)) ? array_values($this->values) : $this->values;
        $terms = [$this->field => $values];

        if ($this->field !== 'boost') {
            $terms['boost'] = $this->boost;
        } elseif ($this->boost !== 1.0) {
            return $this->wrapWithBoost(['terms' => $terms]);
        }

        return ['terms' => $terms];
    }
}
