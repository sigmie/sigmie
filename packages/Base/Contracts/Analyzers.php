<?php

namespace Sigmie\Base\Contracts;

use Sigmie\Support\Contracts\Collection as CollectionInterface;

interface Analyzers
{
    public function analyzers(): CollectionInterface;
}
