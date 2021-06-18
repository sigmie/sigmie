<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs\Calls;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\RequiresIndexAware;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Reindex
{
    use API, RequiresIndexAware;

    public function reindexAPICall(string $source, string $dest): ElasticsearchResponse
    {
        $body = [
            'source' => ['index' => $source],
            'dest' => ['index' => $dest],
        ];

        $uri = new Uri("/_reindex");
        $uri = Uri::withQueryValue($uri, 'refresh', 'true');

        $esRequest = new ElasticsearchRequest('POST', $uri, $body);

        return $this->httpCall($esRequest);
    }
}
