<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;

class SearchableNumber extends Text implements Analyze
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

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer->tokenizeOnWordBoundaries();
        $newAnalyzer->lowercase();
        $newAnalyzer->patternReplace('[^0-9]', '');

        $this->makeSortable();
    }

    public function queries(array|string $queryString): array
    {
        $queries = [];

        $queries[] = new Match_($this->name, $queryString);
        $queries[] = new MatchPhrasePrefix($this->name, $queryString);

        return $queries;
    }
}
