<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;

class Category extends Text
{
    public function configure(): void
    {
        $this->unstructuredText()->keyword();
    }

    public function isAutocompletable(): bool
    {
        return true;
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $this->makeSortable();
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Match_($this->name, $queryString);
        $queries[] = new Term("{$this->name}.keyword", $queryString);
        $queries[] = new MatchPhrasePrefix($this->name, $queryString);

        return $queries;
    }
}
