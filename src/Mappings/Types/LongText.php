<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Query\Queries\Text\Match_;

class LongText extends Text
{
    public function __construct(
        string $name,
    ) {
        parent::__construct($name, raw: null);
    }

    public function configure(): void
    {
        $this->unstructuredText();
    }

    public function queries(array|string $queryString): array
    {
        return $this->queryStrings($queryString, fn (string $queryString): array => [new Match_($this->name(), $queryString, analyzer: $this->searchAnalyzer())]);
    }
}
