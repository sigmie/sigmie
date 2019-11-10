<?php

declare(strict_types=1);

namespace Sigma\Document\Response;

use Closure;
use Sigma\Common\Bootable;
use Sigma\Contract\BootableResponse;
use Sigma\Document\Action\Get as GetAction;
use Sigma\Document\Response\Get as GetResponse;

class Merge implements BootableResponse
{
    use Bootable;

    public function prepare(array $raw)
    {
        return $this->execute(
            new GetAction,
            new GetResponse,
            $raw['_index'],
            $raw['_id'],
            $raw['_type']
        );
    }

    /**
     * Result formating method
     *
     * @param array $raw
     *
     * @return Element
     */
    public function result($document, Closure $boot)
    {
        dump($document);
        die();
        return $document;
    }
}
