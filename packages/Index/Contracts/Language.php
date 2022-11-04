<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

interface Language
{
    public function builder(ElasticsearchConnection $httpConnection): LanguageBuilder;
}
