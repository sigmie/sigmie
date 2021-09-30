<?php

declare(strict_types=1);

namespace Sigmie\German;

use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\LanguageBuilder;

class German implements Language
{
    public function builder(HttpConnection $httpConnection): LanguageBuilder
    {
        return new Builder($httpConnection);
    }
}
