<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Exception;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Exceptions\BulkException;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchResponse;

class Bulk extends ElasticsearchResponse
{
    public function exception(ElasticsearchRequest $request): Exception
    {
        if ($this->json('error')) {
            return ElasticsearchException::fromType($this->json('error.root_cause.type'), $this->json());
        }

        $items = $this->json()['items'] ?? [];

        return BulkException::fromItems($items);
    }

    public function failed(): bool
    {
        return parent::failed() || $this->code() === 400;
    }

    public function items(): array
    {
        return $this->json('items');
    }
}
