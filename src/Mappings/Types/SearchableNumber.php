<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;

class SearchableNumber extends Text
{
    public function __construct(
        string $name,
    ) {
        parent::__construct($name);
    }

    public function configure(): void
    {
        $this->unstructuredText()->keyword();
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Match_($this->name, $queryString);
        $queries[] = new MatchPhrasePrefix($this->name, $queryString);

        return $queries;
    }
}
