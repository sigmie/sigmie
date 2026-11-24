<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\TokenFilter\SynonymGraph;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\CustomAnalyzer;
use Sigmie\Index\Contracts\TokenFilter;

trait SearchSynonyms
{
    protected ?array $searchSynonyms = null;

    public function searchSynonyms(array $synonyms): self
    {
        $this->searchSynonyms = $synonyms;

        return $this;
    }

    protected function makeSearchSynonymsAnalyzer(Analysis $analysis, CustomAnalyzer $default): Analyzer
    {
        $analyzer = new Analyzer(
            name: 'default_with_synonyms',
        );

        $configured = dot([]);
        $configured->set($this->config ?? []);
        $configuredFilters = (array) $configured->get('analysis.filter', []) + (array) $configured->get('index.analysis.filter', []);

        $name = 'search_synonyms';
        for ($suffix = 2; $analysis->hasFilter($name) || isset($configuredFilters[$name]); $suffix++) {
            $name = 'search_synonyms_'.$suffix;
        }
        $graph = new SynonymGraph($name, $this->searchSynonyms);

        // Lowercase and folding run before the synonym list, so a rule
        // written in lowercase matches the token.
        $analyzer->addCharFilters($default->charFilters());
        $filters = $default->filters();
        $afterGraph = ['stop', 'keep', 'keep_types', 'length', 'unique'];
        // These index filters cannot parse synonym rules.
        $analyzer->addFilters(array_filter(
            $filters,
            fn (TokenFilter $filter): bool => ! in_array($filter->type(), [
                'shingle', 'ngram', 'edge_ngram', 'word_delimiter', 'word_delimiter_graph',
                'multiplexer', 'fingerprint', 'common_grams', 'min_hash', 'limit',
                ...$afterGraph,
            ], true),
        ));
        $analyzer->addFilters([$graph]);
        // Filters that drop terms run after expansion so rules keep their positions.
        $analyzer->addFilters(array_filter($filters, fn (TokenFilter $filter): bool => in_array($filter->type(), $afterGraph, true)));
        $analyzer->setTokenizer($default->tokenizer());

        return $analyzer;
    }
}
