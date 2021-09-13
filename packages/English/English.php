<?php

declare(strict_types=1);

namespace Sigmie\English;

use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\LanguageBuilder;

class English implements Language
{
    public function builder(HttpConnection $httpConnection): LanguageBuilder
    {
        return new Builder($httpConnection);
    }
}
