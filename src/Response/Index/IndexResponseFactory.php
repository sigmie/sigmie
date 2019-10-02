<?php

namespace Ni\Elastic\Response\Index;

use Ni\Elastic\Response\FailureResponse;
use Ni\Elastic\Response\Response;
use Ni\Elastic\Response\SuccessResponse;

class IndexResponseFactory implements ResponseFactory
{
    public function create(array $result): Response
    {
        if (true) {
            return $this->createSuccess($result);
        }

        return $this->createFailure($result);
    }

    private function createFailure(array $result): FailureResponse
    {
        return new IndexFailureResponse();
    }

    private function createSuccess(array $result): SuccessResponse
    {
        return new IndexSuccessResponse();
    }
}
