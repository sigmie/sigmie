<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Exception;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseInterface;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Base\Exceptions\FailedToBuildSynonyms;
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

        $exception = match ($message) {
            'Failed to build synonyms' => new FailedToBuildSynonyms($request, $this, $message),
            default => new ElasticsearchException($request, $this, $message)
        };

        return $exception;
    }

    private function hasErrorKey(): bool
    {
        return !is_null($this->json('error'));
    }
}
