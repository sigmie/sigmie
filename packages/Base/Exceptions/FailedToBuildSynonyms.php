<?php

declare(strict_types=1);

namespace Sigmie\Base\Exceptions;

use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Http\ElasticsearchResponse;

class FailedToBuildSynonyms extends ElasticsearchException
{
    private string $reason;

    private array $filters;

    public function __construct(
        protected ElasticsearchRequest $request,
        protected ElasticsearchResponse $response,
        ?string $message = null,
    ) {
        $this->reason = $response->json('error')['caused_by']['caused_by']['reason'];

        $this->filters = $this->request->body()['settings']['analysis']['filter'];

        $message = $message . ". Reason: '{$this->reason}'.";

        parent::__construct($request, $response, $message);
    }
}
