<?php

declare(strict_types=1);

namespace Sigmie\Languages\German\Filter;

use Sigmie\Index\Analysis\TokenFilter\Lowercase as BaseLowercase;

class Lowercase extends BaseLowercase
{
    public function __construct(string $name = 'german_lowercase')
    {
        parent::__construct($name);
    }
}
