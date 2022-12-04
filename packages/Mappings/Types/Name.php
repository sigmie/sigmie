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
    public function __construct(
        string $name,
        protected int $minGrams = 4,
        protected int $maxGrams = 5
    ) {
        parent::__construct($name);
    }

    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes();
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $prefixField = (new Text("{$this->name}_text"))->unstructuredText()->withNewAnalyzer(function (NewAnalyzer $newAnalyzer) {

            $newAnalyzer->tokenizeOnWordBoundaries()
                ->truncate($this->minGrams - 1)
                ->lowercase()
                ->trim();
        });

        $this->field($prefixField);

        $newAnalyzer
            ->tokenizeOnWordBoundaries()
            ->tokenFilter(new Ngram("{$this->name}_ngram", $this->minGrams, $this->maxGrams))
            // ->truncate($this->maxGramms)
            ->lowercase();
    }

    public function names(): array
    {
        return [
            $this->name,
            "{$this->name}.{$this->name}_text"
        ];
    }


    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Match_($this->name, $queryString);
        $queries[] = new Prefix("{$this->name}.{$this->name}_text", $queryString);

        return $queries;
    }
}
