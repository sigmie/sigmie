<?php

declare(strict_types=1);

namespace Sigmie\German;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Index\Contracts\Language;
use Sigmie\Index\Contracts\LanguageBuilder;

class German implements Language
{
    public function builder(ElasticsearchConnection $httpConnection): LanguageBuilder
    {
        return new Builder($httpConnection);
    }
}
