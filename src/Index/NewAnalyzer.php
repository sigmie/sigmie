<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\CustomAnalyzer;
use Sigmie\Index\Shared\CharFilters;
use Sigmie\Index\Shared\Filters;
use Sigmie\Index\Shared\Tokenizer;

class NewAnalyzer
{
    use CharFilters;
    use Filters;
    use Tokenizer;

    protected Analyzer $pipeline;

    public function __construct(protected AnalysisInterface $analysis, public string $name)
    {
        $this->pipeline = new Analyzer($name);
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function analysis(): AnalysisInterface
    {
        return $this->analysis;
    }

    protected function pipeline(): CustomAnalyzer
    {
        return $this->pipeline;
    }

    public function create(): CustomAnalyzer
    {
        $analyzer = clone new Analyzer(
            $this->name,
            $this->pipeline->tokenizer(),
            $this->filters(),
            $this->charFilters(),
        );
        $this->analysis()->addAnalyzer($analyzer);

        return $analyzer;
    }
}
