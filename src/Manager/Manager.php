<?php

namespace Ni\Elastic\Manager;

use Ni\Elastic\Contract\Manager as ManagerInterface;
use Ni\Elastic\Index\Manager as IndexManager;

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
