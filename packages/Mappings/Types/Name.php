<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\Analysis\TokenFilter\Ngram;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Mappings\Contracts\Configure;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Text\Match_;

class Name extends Text implements Analyze, Configure
{
    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes();
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $maxGramms = 4;

        $prefixField = (new Text("{$this->name}_text"))->unstructuredText()->withNewAnalyzer(function (NewAnalyzer $newAnalyzer) {

            $newAnalyzer->tokenizeOnWordBoundaries()
                ->lowercase()
                ->trim();
        });

        $this->field($prefixField);

        $newAnalyzer
            ->tokenizeOnWordBoundaries()
            ->tokenFilter(new Ngram("{$this->name}_ngram_3_4", 3, $maxGramms))
            ->truncate($maxGramms)
            ->lowercase();
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Prefix($this->name, $queryString);
        $queries[] = new Match_($this->name, $queryString);

        $queries[] = new Match_("{$this->name}_text", $queryString);
        $queries[] = new Prefix("{$this->name}_text", $queryString);

        return $queries;
    }
}
