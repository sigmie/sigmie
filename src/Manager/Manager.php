<?php

namespace Sigma\Manager;

use Sigma\Contract\Manager as ManagerInterface;
use Sigma\Index\Manager as IndexManager;

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
