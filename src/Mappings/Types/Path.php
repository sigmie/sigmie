<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;

class Path extends Text implements Analyze
{
    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer
            ->tokenizePathHierarchy()
            ->lowercase();
    }

    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes()->keyword();
    }

    public function queries(array|string $queryString): array
    {
        // The whole path is the deepest path_hierarchy token. A match query on all tokens
        // ORs them on Elasticsearch 8, where Lucene 9 stacks them at one position.
        return $this->queryStrings($queryString, fn (string $queryString): array => [
            ...$this->prefixQueries($this->name(), $this->lowercaseTerm($queryString)),
            ...$this->termQueries($this->name(), $this->lowercaseTerm($queryString)),
        ]);
    }
}
