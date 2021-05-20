<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseInterface;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Http\JSONResponse;

class ElasticsearchResponse extends JSONResponse implements ElasticsearchResponseInterface
{
    public function __construct(ResponseInterface $psr)
    {
        parent::__construct($psr);
    }

    public function failed(): bool
    {
        return $this->serverError() || $this->clientError() || $this->hasErrorKey();
    }

    public function exception(ElasticsearchRequest $request): Exception
    {
        return  new ElasticsearchException($request, $this);
    }

    private function hasErrorKey()
    {
        return !is_null($this->json('error'));
    }
}
