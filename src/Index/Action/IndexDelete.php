<?php

namespace Ni\Elastic\Index\Action;

use Ni\Elastic\Action\Delete;

class IndexDelete implements Delete
{
    public function response($response):bool
    {
        return $response['acknowledged'];
    }
}
