<?php

namespace Ni\Elastic\Response;

/**
 * Response abstract factory
 */
abstract class ResponseFactory
{
    abstract function indexResponse();

    abstract function mappingResponse();
}
