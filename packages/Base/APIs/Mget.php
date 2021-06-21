<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Http\Requests\Mget as MgetRequest;
use Sigmie\Base\Http\Responses\Mget as MgetResponse;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\RequiresIndexAware;

trait Mget
{
    use API, RequiresIndexAware;

    public function mgetAPICall(array $body = []): MgetResponse
    {
        $indexName = $this->index()->name();
        $uri = new Uri("/{$indexName}/_mget");

        $esRequest = new MgetRequest('POST', $uri, $body);

        return $this->httpCall($esRequest);
    }
}
