<?php

namespace Sigma\Contract;

interface Type
{
    /**
     * Elasticsearch field name
     *
     * @return string
     */
    public function field(): string;

    /**
     * Default type parameters
     *
     * @return array
     */
    public function parameters(): array;
}