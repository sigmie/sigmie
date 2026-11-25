<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\Http\ElasticsearchResponse;

class Delete extends ElasticsearchResponse
{
    public function failed(): bool
    {
        if ($this->code() === 404 && $this->json('result') === 'not_found') {
            return false;
        }

        return parent::failed();
    }
}
