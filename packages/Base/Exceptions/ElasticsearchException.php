<?php

declare(strict_types=1);

namespace Sigmie\Base\Exceptions;

use Exception;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Http\ElasticsearchResponse;

class ElasticsearchException extends Exception
{
    protected ElasticsearchRequest $request;

    protected ElasticsearchResponse $response;

    public function __construct(ElasticsearchRequest $request, ElasticsearchResponse $response)
    {
        parent::__construct(ucfirst($response->json()['error']['reason']) . '.', 1);

        $this->request = $request;
        $this->response = $response;
    }
}
