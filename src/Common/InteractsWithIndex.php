<?php

namespace Sigma\Common;

use Sigma\Element;
use Sigma\Collection;
use Sigma\Common\Bootable;
use Sigma\Index\Action\Get as GetAction;
use Sigma\Index\Action\Insert as InsertAction;
use Sigma\Index\Action\Remove as RemoveAction;
use Sigma\Index\Action\Listing as ListingAction;
use Sigma\Index\Response\Get as GetResponse;
use Sigma\Index\Response\Insert as InsertResponse;
use Sigma\Index\Response\Remove as RemoveResponse;
use Sigma\Index\Response\Listing as ListingResponse;

trait InteractsWithIndex
{
    use Bootable;

    /**
     * Dispatch the index insert action and
     * pass the response to the handler
     *
     * @param Element $index
     *
     * @return boolean
     */
    public function insert(Element $index): Element
    {
        return $this->execute(new InsertAction, new InsertResponse, $index);
    }

    /**
     * Dispatch the index remove action and
     * pass the response to the handler
     *
     * @param string $identifier
     *
     * @return boolean
     */
    public function remove(string $identifier): bool
    {
        return $this->execute(new RemoveAction, new RemoveResponse, $identifier);
    }

    /**
     * Dispatch the index listing action and
     * pass the response to the handler
     *
     * @param string $name
     *
     * @return Collection
     */
    public function list(string $name = '*'): Collection
    {
        return $this->execute(new ListingAction, new ListingResponse, $name);
    }

    /**
     * Dispatch the index get action and
     * pass the response to the handler
     *
     * @param string $name
     *
     * @return Element
     */
    public function get(string $name): Element
    {
        return $this->execute(new GetAction, new GetResponse, $name);
    }
}
