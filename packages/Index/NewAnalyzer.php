<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\CustomAnalyzer;
use Sigmie\Index\Shared\CharFilters;
use Sigmie\Index\Shared\Filters;
use Sigmie\Index\Shared\Tokenizer;
use Sigmie\Shared\Name;

class NewAnalyzer
{
    use Filters;
    use CharFilters;
    use Tokenizer;
    use Name;

    public function __construct(protected AnalysisInterface $analysis, string $name)
    {
        $this->name = $name;

        $this->tokenizer = new WordBoundaries();
    }

    public function analysis(): AnalysisInterface
    {
        return $this->analysis;
    }

    public function create(): CustomAnalyzer
    {
        $analyzer = new Analyzer($this->name);
        $analyzer->addCharFilters($this->charFilters());
        $analyzer->addFilters($this->filters());
        $analyzer->setTokenizer($this->tokenizer);

        $this->analysis()->addAnalyzers([$analyzer]);

        return $analyzer;
    }
}
