<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Testing\Assertions\Analyzer;
use Sigmie\Testing\Assertions\CharFilter;
use Sigmie\Testing\Assertions\Filter;
use Sigmie\Testing\Assertions\Index;
use Sigmie\Testing\Assertions\Mapping;
use Sigmie\Testing\Assertions\Settings;
use Sigmie\Testing\Assertions\Tokenizer;
use Sigmie\Testing\Shared\IndexData;

trait Assertions
{
    use Settings, Mapping, Index, Analyzer, Tokenizer, CharFilter, Filter;
}
