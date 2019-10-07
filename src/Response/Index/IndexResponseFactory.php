<?php

namespace Ni\Elastic\Response\Index;

use Ni\Elastic\Response\Factory;
use Ni\Elastic\Response\FailureResponse;
use Ni\Elastic\Response\Response;
use Ni\Elastic\Response\SuccessResponse;
use Ni\Elastic\Index\Index;
use Ni\Elastic\Index\IndexCollection;

class IndexResponseFactory implements Factory
{
    public function create(array $result): Response
    {
        if (isset($result['error'])) {
            return $this->createFailure($result);
        }

        return $this->createSuccess($result);
    }

    private function createFailure(array $result): FailureResponse
    {
        return new IndexFailureResponse();
    }

    private function createSuccess(array $result): SuccessResponse
    {
        $response = null;

        if (isset($result['acknowledged'])) {
            $response = new IndexSuccessResponse($result['acknowledged']);
        }

        if (isset($result['index'])) {
            $index = new Index($result['index']);
            $response->setElement($index);
        }

        if ($response === null) {
            $response = new IndexCollection($result);
        }

        return $response;
    }
}