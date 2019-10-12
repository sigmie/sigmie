<?php

namespace Ni\Elastic\Manager;

use Ni\Elastic\Element;
use Ni\Elastic\Collection;
use Ni\Elastic\Contract\ResponseHandler;
use Ni\Elastic\Contract\Manager as ManagerInterface;
use Ni\Elastic\Index\Manager as IndexManager;
use Ni\Elastic\Index\Action\Get as GetAction;
use Ni\Elastic\Index\Action\Create as CreateAction;
use Ni\Elastic\Index\Action\Remove as RemoveAction;
use Ni\Elastic\Index\Action\Listing as ListingAction;
use Ni\Elastic\Index\Response\Get as GetResponse;
use Ni\Elastic\Index\Response\Create as CreateResponse;
use Ni\Elastic\Index\Response\Remove as RemoveResponse;
use Ni\Elastic\Index\Response\Listing as ListingResponse;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\ActionDispatcher;

class Manager
{
    /**
     * Index Manager
     *
     * @var IndexManager
     */
    private $index;

    public function __set($name, ManagerInterface $value)
    {
        $this->$name = $value;
    }

    public function indices(): IndexManager
    {
        return $this->index;
    }
}
