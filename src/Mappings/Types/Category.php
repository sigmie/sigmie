<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchBoolPrefix;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;

class Category extends Text
{
    public function configure(): void
    {
        $this->unstructuredText()->keyword();

        $this->makeSortable();
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
        $keyword = $this->keywordName();

        return $this->queryStrings($queryString, fn (string $queryString): array => [
            new Match_($this->name(), $queryString, analyzer: $this->searchAnalyzer()),
            ...($keyword === null ? [] : $this->termQueries($keyword, $queryString)),
            new MatchPhrasePrefix($this->name(), $queryString, analyzer: $this->searchAnalyzer()),
            new MatchBoolPrefix($this->name(), $queryString, analyzer: $this->searchAnalyzer()),
        ]);
    }
}
