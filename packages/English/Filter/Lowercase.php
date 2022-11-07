<?php

declare(strict_types=1);

namespace Sigmie\English\Filter;

use Sigmie\Index\Analysis\TokenFilter\Lowercase as BaseLowercase;

class Lowercase extends BaseLowercase
{
    public function __construct(
        string $name = 'english_lowercase')
    {
        parent::__construct($name);
    }
}
