<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Shared\Priority;

class HTMLFilter implements CharFilter
{
    use Priority;

    public function name(): string
    {
        return 'html_strip';
    }
}
