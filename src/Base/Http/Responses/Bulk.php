<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\Http\ElasticsearchResponse;

class Bulk extends ElasticsearchResponse
{
    public function failed(): bool
    {
        if (parent::failed()) {
            return true;
        }

        return $this->code() === 400;
    }

    public function items(): array
    {
        return $this->json('items');
    }
}
