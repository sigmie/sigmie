<?php

declare(strict_types=1);

namespace Sigmie\German\Filter;

use Sigmie\Base\Analysis\TokenFilter\Lowercase as BaseLowercase;

use function Sigmie\Helpers\name_configs;

class Lowercase extends BaseLowercase
{
    public function __construct(string $name = 'german_lowercase')
    {
        parent::__construct($name);
    }
}
