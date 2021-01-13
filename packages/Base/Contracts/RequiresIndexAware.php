<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Index\Index;

trait RequiresIndexAware
{
    abstract protected function index(): Index;
}
