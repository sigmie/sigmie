<?php

namespace Sigma\Provider;

use Sigma\Common\InteractsWithIndex;
use Sigma\Provider\EventProvider;

class SigmaProvider extends EventProvider
{
    use InteractsWithIndex;

    public function __construct()
    {
        parent::__construct();
    }
}
