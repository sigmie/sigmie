<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\TokenFilter\SynonymGraph;

trait SearchSynonyms
{
    protected ?array $searchSynonyms = null;

    public function searchSynonyms(array $synonyms): self
    {
        $this->searchSynonyms = $synonyms;

        return $this;
    }

    protected function makeSearchSynonymsAnalyzer(): Analyzer
    {
        $analyzer = new Analyzer(
            name: 'default_with_synonyms',
        );

        $graph = new SynonymGraph('search_synonyms', $this->searchSynonyms);

        $analyzer->addFilters([$graph]);

        // Inherit the default analyzer settings
        $analyzer->addCharFilters($this->charFilters());
        $analyzer->addFilters($this->filters());
        $analyzer->setTokenizer($this->tokenizer);

        return $analyzer;
    }
}
