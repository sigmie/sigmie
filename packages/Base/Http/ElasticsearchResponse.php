<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Exception;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseInterface;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Base\Exceptions\FailedToBuildSynonyms;
use Sigmie\Base\Exceptions\IndexNotFound;
use Sigmie\Http\JSONResponse;

class ElasticsearchResponse extends JSONResponse implements ElasticsearchResponseInterface
{
    public function failed(): bool
    {
        return $this->serverError() || $this->clientError() || $this->hasErrorKey();
    }

    public function exception(ElasticsearchRequest $request): Exception
    {
        $json = $this->json();
        $message = json_encode($json);

        if (is_null($json)) {
            $message = "Request failed with code {$this->code()}.";
        }

        if (isset($json['error']) && is_string($json['error'])) {
            $message = $json['error'];
        }

        if (isset($json['error']) && is_array($json['error'])) {
            $message = ucfirst($json['error']['reason']);
        }

        if (isset($json['error']) && isset(($json['error']['caused_by']))) {
            $message = $json['error']['caused_by']['reason'];
        }
        dd($json);

        $exception = match (true) {
            $message === 'Failed to build synonyms' => new FailedToBuildSynonyms($request, $this, $message),
            str_starts_with($message, 'No such index') => new IndexNotFound($request, $this, $message),
            default => new ElasticsearchException($request, $this, $message)
        };

        return $exception;
    }

    private function hasErrorKey(): bool
    {
        return !is_null($this->json('error'));
    }
}
