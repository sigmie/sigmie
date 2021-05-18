<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface Languageable
{
    public function language(Language $language): void;
}
