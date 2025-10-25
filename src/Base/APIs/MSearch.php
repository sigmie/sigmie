<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Http\Requests\MSearch as RequestsMSearch;

trait MSearch
{
    use API;

    protected function msearchAPICall(array $body): ElasticsearchResponse
    {
        $uri = new Uri('/_msearch');

        $esRequest = new RequestsMSearch('POST', $uri, $body);

        return $this->elasticsearchCall($esRequest);
    }
}
