<?php

declare(strict_types=1);

namespace Sigmie\Base\Exceptions;

use Exception;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Http\ElasticsearchResponse;

class ElasticsearchException extends Exception
{
    public function __construct(
        protected ElasticsearchRequest $request,
        protected ElasticsearchResponse $response,
        ?string $message = null,
    ) {

        $message = $message ?? ucfirst($response->json()['error']['reason']) . '.';

        parent::__construct($message, $response->code());
    }
}
