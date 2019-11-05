<?php

namespace Sigma\Document\Response;

use Sigma\Contract\Response;

class Get implements Response
{
    /**
     * Result formating method
     *
     * @param array $raw
     *
     * @return Element
     */
    public function result($data)
    {
        return;
    }
}
