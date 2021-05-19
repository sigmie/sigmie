<?php

declare(strict_types=1);

namespace Sigmie\Base\Exceptions;

use Exception;

class MissingMapping extends Exception
{
    public function __construct()
    {
        parent::__construct('Index mapping is missing.');
    }
}
