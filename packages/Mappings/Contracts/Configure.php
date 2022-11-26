<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Contracts;

use Sigmie\Index\NewAnalyzer;

interface Configure
{
    public function configure(): void;
}
