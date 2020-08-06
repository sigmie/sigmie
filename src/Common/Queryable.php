<?php

declare(strict_types=1);


namespace Sigma\Common;

use Sigma\Exception\NotImplementedException;

trait TraitName
{
    public function query()
    {
        throw new NotImplementedException();
    }
}
