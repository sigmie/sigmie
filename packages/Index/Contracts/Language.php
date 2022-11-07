<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

use Sigmie\Base\Contracts\ElasticsearchConnection;

interface Language
{
    public function builder(ElasticsearchConnection $httpConnection): LanguageBuilder;
}
