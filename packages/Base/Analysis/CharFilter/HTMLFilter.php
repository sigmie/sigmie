<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Sigmie\Base\Contracts\CharFilter;

class HTMLFilter implements CharFilter
{
    public function name(): string
    {
        return 'html_strip';
    }
}
