<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Support\Contracts\Collection;

interface Language
{
    public function builder(HttpConnection $httpConnection): LanguageBuilder;
}
