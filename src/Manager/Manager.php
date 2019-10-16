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

    /**
     * Magic set method
     *
     * @param string $name
     * @param ManagerInterface $value
     */
    public function __set(string $name, ManagerInterface $value)
    {
        $this->$name = $value;
    }

    /**
     * Index manager instance
     *
     * @return IndexManager
     */
    public function indices(): IndexManager
    {
        return $this->index;
    }
}
