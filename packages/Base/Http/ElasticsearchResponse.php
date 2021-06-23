<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Exception;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseInterface;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Http\JSONResponse;

class ElasticsearchResponse extends JSONResponse implements ElasticsearchResponseInterface
{
    public function failed(): bool
    {
        return $this->serverError() || $this->clientError() || $this->hasErrorKey();
    }

    public function exception(ElasticsearchRequest $request): Exception
    {
        $message = is_null($this->json()) ? "Request failed with code {$this->code()}." : ucfirst($this->json()['error']['reason']);

        return  new ElasticsearchException($request, $this, $message);
    }

    private function hasErrorKey()
    {
        return !is_null($this->json('error'));
    }
}
