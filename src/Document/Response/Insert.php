<?php

namespace Sigma\Document\Response;

use Sigma\Common\Bootable;
use Sigma\Contract\BootableResponse;
use Sigma\Document\Action\Get as GetAction;
use Sigma\Document\Response\Get as GetResponse;

class Insert implements BootableResponse
{
    use Bootable;

    public function prepare(array $raw)
    {
        return $this->execute(new GetAction, new GetResponse, $raw['_index'], $raw['_id'], $raw['_type']);
    }
    /**
     * Result formating method
     *
     * @param array $raw
     *
     * @return Element
     */
    public function result($document)
    {
        return $document;
    }
}
