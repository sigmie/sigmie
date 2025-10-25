<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Exception;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseInterface;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Http\JSONResponse;

class ElasticsearchResponse extends JSONResponse implements ElasticsearchResponseInterface
{
    public function failed(): bool
    {
        if ($this->serverError()) {
            return true;
        }

        if ($this->clientError()) {
            return true;
        }

        return $this->hasErrorKey();
    }

    public function exception(ElasticsearchRequest $request): Exception
    {
        $type = null;

        if (is_null($this->json())) {
            $type = sprintf('Request failed with code %d.', $this->code());
        }

        if (is_string($this->json('error'))) {
            $type = $this->json('error');
        }

        if (is_string($this->json('error.type'))) {
            $type = $this->json('error.type');
        }

        if (is_string($this->json('error.caused_by.type'))) {
            $type = $this->json('error.caused_by.type');
        }

        if (is_string($this->json('error.root_cause.0.type'))) {
            $type = $this->json('error.root_cause.0.type');
        }

        if (is_string($this->json('failures.0.cause.type'))) {
            $type = $this->json('failures.0.cause.type');
        }

        return new ElasticsearchException([
            'type' => $type,
            'code' => $this->code(),
            'json' => $this->json(),
            'body' => $this->body(),
            'request' => json_decode($request->getBody()->getContents(), true),
        ], $this->code());
    }

    private function hasErrorKey(): bool
    {
        return ! is_null($this->json('error'));
    }
}
