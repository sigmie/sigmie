<?php

namespace Ni\Elastic\Index;

use Ni\Elastic\Element;

class Index extends Element
{
    /**
     * Identifier
     *
     * @var string 
     */
    private $identifier;

    public function __construct(?string $identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Get the value of identifier
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Set the value of identifier
     *
     * @return  self
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }
}
