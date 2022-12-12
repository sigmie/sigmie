<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\Analysis\TokenFilter\Ngram as TokenFilterNgram;
use Sigmie\Index\Analysis\Tokenizers\Ngram;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Text\MatchBoolPrefix;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;

class Title extends Text implements Analyze
{
    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer
            ->tokenizeOnWordBoundaries()
            ->lowercase();
    }

    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes();
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Prefix($this->name, $queryString);
        $queries[] = new Match_($this->name, $queryString);

        return $queries;
    }
}
