<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Contracts;

use Sigmie\Index\NewAnalyzer;

interface Analyze
{
    public function analyze(NewAnalyzer $newAnalyzer): void;
}
