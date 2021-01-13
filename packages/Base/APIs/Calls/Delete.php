<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs\Calls;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\RequiresIndexAware;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Http\JsonRequest;

trait Delete
{
    use API, RequiresIndexAware;

    protected function deleteAPICall(string $identifier, bool $async = false): ElasticsearchResponse
    {
        $uri = new Uri("/{$this->index()->getName()}/_doc/{$identifier}");

        if (!$async) {
            $uri = Uri::withQueryValue($uri, 'refresh', 'wait_for');
        }

        $esRequest = new JsonRequest('DELETE', $uri);

        return $this->call($esRequest);
    }
}
