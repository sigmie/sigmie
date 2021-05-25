<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs\Calls;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Http\ElasticsearchResponse;

trait Settings
{
    use API;

    public function settingsAPICall(string $index): ElasticsearchResponse
    {
        $uri = new Uri($index);

        $esRequest = new ElasticsearchRequest('GET', $uri);

        return $this->httpCall($esRequest);
    }
}
