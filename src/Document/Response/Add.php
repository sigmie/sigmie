<?php

namespace Sigma\Document\Response;

use Sigma\Contract\Response;

class Add implements Response
{
    /**
     * Result formating method
     *
     * @param array $raw
     *
     * @return Element
     */
    public function result(array $raw)
    {
        dump($raw);
        die();
    }
}
