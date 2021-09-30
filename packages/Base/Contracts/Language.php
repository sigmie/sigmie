<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;


interface Language
{
    public function builder(HttpConnection $httpConnection): LanguageBuilder;
}
