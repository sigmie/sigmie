<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Exceptions\BulkException;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Base\Exceptions\ReindexException;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

class Reindex extends ElasticsearchResponse
{
    public function exception(ElasticsearchRequest $request): ElasticsearchException
    {
        return ReindexException::firstReason($request, $this);
    }
}
