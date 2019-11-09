<?php

declare(strict_types=1);


namespace Sigma\Document\Response;

use Sigma\Contract\Response;
use Closure;

class Remove implements Response
{
    public function result($response, Closure $boot): bool
    {
        return $response['result'] === 'deleted';
    }
}
