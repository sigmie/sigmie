<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Exception;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Http\JsonResponse;

class ElasticsearchResponse extends JsonResponse
{
    public function __construct(ResponseInterface $psr)
    {
        parent::__construct($psr);
    }

    public function failed()
    {
        return $this->serverError() || $this->clientError() || $this->hasErrorKey();
    }

    public function exception(Request $request): Exception
    {
        return  new ElasticsearchException($request, $this);
    }

    private function hasErrorKey()
    {
        return !is_null($this->json('error'));
    }
}
