<?php

namespace Ni\Elastic;

abstract class Element
{
    protected $created = false;

    public function exists(): bool
    {
        return $this->created;
    }


    /**
     * Get the value of created
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set the value of created
     *
     * @return  self
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }
}
