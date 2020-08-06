<?php

declare(strict_types=1);


namespace Sigma\Document\Response;

use Closure;
use Sigma\Contract\Response;
use Sigma\Document\Document;
use Sigma\Document\Factory as DocumentFactory;

class Get implements Response
{
    /**
     * Result formating method
     *
     * @param array $raw
     *
     * @return Element
     */
    public function result($data, Closure $boot)
    {
        $factory = new DocumentFactory();

        return $factory->fromRaw($data);
    }
}
