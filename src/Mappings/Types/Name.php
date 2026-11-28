<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\Analysis\TokenFilter\Ngram;
use Sigmie\Index\Analysis\TokenFilter\Synonyms;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchBoolPrefix;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;

use function Sigmie\Functions\random_name;

class Name extends Text implements Analyze
{
    protected string $prefixAnalyzer = 'name_text_field_analyzer';

    protected string $ngramAnalyzer = 'name_ngram_field_analyzer';

    public function __construct(
        string $name,
        protected int $minGrams = 4,
        protected int $maxGrams = 5
    ) {
        parent::__construct($name);

        $this->prefixAnalyzer = 'name_text_field_analyzer_'.$minGrams;
        $this->ngramAnalyzer = 'name_ngram_field_analyzer_'.$minGrams.'_'.$maxGrams;
    }

    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes()->keyword();

        // prefixAnalyzer is assigned after the parent constructor, which calls configure().
        $prefixAnalyzer = 'name_text_field_analyzer_'.$this->minGrams;

        $this->field(
            (new Text($this->prefixFieldName()))->unstructuredText()
                ->withNewAnalyzer(function (NewAnalyzer $newAnalyzer) use ($prefixAnalyzer): void {
                    $newAnalyzer->tokenizeOnWhitespaces()
                        ->name($prefixAnalyzer)
                        ->lowercase()
                        ->trim();
                })
        );

        $this->makeSortable();
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        $newAnalyzer
            ->tokenizeOnWhitespaces()
            ->name($this->ngramAnalyzer)
            ->tokenFilter(new Ngram(random_name('ngram'), $this->minGrams, $this->maxGrams))
            // ->truncate($this->maxGramms)
            ->lowercase();
    }

    private function prefixFieldName(): string
    {
        $parts = explode('.', $this->name);

        return end($parts).'_text';
    }

    public function names(): array
    {
        $prefix = $this->prefixFieldName();

        return $this->fields->get($prefix) === null
            ? [$this->fullPath()]
            : [$this->fullPath(), $this->fullPath().'.'.$prefix];
    }

    public function queries(array|string $queryString): array
    {
        return $this->queryStrings($queryString, function (string $queryString): array {
            $names = $this->names();
            $analyzer = $this->searchAnalyzer === null && $this->restoredType === null && ! $this->searchSynonyms
                ? $this->ngramAnalyzer
                : $this->searchAnalyzer();
            $match = new Match_($names[0], $queryString, analyzer: $analyzer);
            if (! isset($names[1])) {
                return [$match];
            }

            // The n-gram analyzer stacks its grams, so Elasticsearch drops the fuzziness of $match,
            // and it never fuzzes the last term of a bool_prefix query. The plain match on the
            // whole words carries the typo tolerance.
            return [
                $match,
                new MatchPhrasePrefix($names[1], $queryString, analyzer: $this->prefixAnalyzer),
                new MatchBoolPrefix($names[1], $queryString, analyzer: $this->prefixAnalyzer),
                new Match_($names[1], $queryString, analyzer: $this->prefixAnalyzer),
            ];
        });
    }

    public function notAllowedFilters(): array
    {
        return [
            Synonyms::class,
        ];
    }

    protected static function fromMappingConfig(string $name, array $configs): static
    {
        if (preg_match('/_(\d+)_(\d+)$/', (string) ($configs['analyzer'] ?? ''), $matches) === 1) {
            $field = new static($name, (int) $matches[1], (int) $matches[2]);
        } else {
            $field = new static($name);
        }

        $subfields = (array) ($configs['fields'] ?? []);
        $prefix = (array) ($subfields[$field->prefixFieldName()] ?? []);
        $field->prefixAnalyzer = $prefix['search_analyzer'] ?? $prefix['analyzer'] ?? 'default';

        return $field;
    }
}
