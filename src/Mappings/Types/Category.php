<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
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

    public function queries(array|string $queryString): array
    {
        return [new Match_($this->name, $queryString, analyzer: $this->searchAnalyzer()), new Term($this->name . '.keyword', $queryString), new MatchPhrasePrefix($this->name, $queryString, analyzer: $this->searchAnalyzer())];
    }
}
