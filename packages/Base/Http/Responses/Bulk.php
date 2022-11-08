<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Exception;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Exceptions\BulkException;
use Sigmie\Base\Http\ElasticsearchResponse;

class Bulk extends ElasticsearchResponse
{
    public function failed(): bool
    {
        return parent::failed() || $this->code() === 400;
    }

    public function items(): array
    {
        return $this->json('items');
    }
}
