<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use Exception;
use function Sigmie\Functions\name_configs;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Shared\Contracts\FromRaw;

abstract class AbstractText extends Text
{
    abstract public function configure(): void;

    abstract public function analysis(): void;
}
