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

        $this->makeSortable();
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer->tokenizeOnWordBoundaries();
        $newAnalyzer->lowercase();
        $newAnalyzer->patternReplace('[^0-9]', '');
    }

    public function validate(string $key, mixed $value): array
    {
        if ($this->type() !== 'completion' && (is_int($value) || (is_float($value) && is_finite($value)))) {
            return [true, ''];
        }

        return parent::validate($key, $value);
    }

    public function queries(array|string $queryString): array
    {
        return $this->queryStrings($queryString, fn (string $queryString): array => [new Match_($this->name(), $queryString, analyzer: $this->searchAnalyzer()), new MatchPhrasePrefix($this->name(), $queryString, analyzer: $this->searchAnalyzer())]);
    }
}
