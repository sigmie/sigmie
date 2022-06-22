<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\Http\ElasticsearchResponse;

class Delete extends ElasticsearchResponse
{
    public function failed(): bool
    {
        return $this->serverError();
    }
}
