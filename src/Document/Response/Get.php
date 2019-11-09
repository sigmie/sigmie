<?php

declare(strict_types=1);


namespace Sigma\Document\Response;

use Closure;
use Sigma\Contract\Response;
use Sigma\Document\Document;

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
        $document = new Document($data['_source']);
        $document->id = $data['_id'];

        return $document;
    }
}
