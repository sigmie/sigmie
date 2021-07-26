<?php

declare(strict_types=1);

namespace Sigmie\Base\Exceptions;

use Exception;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;

class ReindexException extends ElasticsearchException
{
    public static function firstReason(
        ElasticsearchRequest $request,
        ElasticsearchResponse $response
    ): static {
        $message = $response->json()['failures'][0]['cause']['reason'];

        return new static($request, $response, ucfirst($message));
    }
}
