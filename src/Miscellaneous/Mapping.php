<?php

namespace Ni\Elastic\Miscellaneous;

trait Mapping
{
    public function populate(array $values)
    {
        foreach ($values as $property => $value) {
            $this->$property = $value;
        }
    }
}
