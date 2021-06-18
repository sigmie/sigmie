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
        if (is_null($response->json())) {
            parent::__construct('Undefined error', $response->code());
        } else {
            parent::__construct(ucfirst($response->json()['error']['reason']) . '.', $response->code());
        }

        $this->request = $request;
        $this->response = $response;
    }
}
