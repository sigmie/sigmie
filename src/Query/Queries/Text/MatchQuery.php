<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Queries\Query;

abstract class MatchQuery extends Query
{
    public function __construct(protected string $query, protected ?string $analyzer = null) {}

    protected function matchOptions(): array
    {
        $options = ['query' => $this->query, 'boost' => $this->boost];

        if ($this->analyzer !== null) {
            $options['analyzer'] = $this->analyzer;
        }

        return $options;
    }
}
