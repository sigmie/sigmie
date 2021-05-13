<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\AliasActions;
use Sigmie\Http\Contracts\JSONRequest;
use Sigmie\Http\JSONClient as HttpJSONClient;
use Sigmie\Http\JSONResponse;

class JSONClient extends HttpJSONClient
{
    use AliasActions;

    private string $foo = '';

    public function request(JSONRequest $jsonRequest): JSONResponse
    {
        $uri = $jsonRequest->getUri();
        $path = $uri->getPath();

        $psrResponse = $this->http->send($jsonRequest);

        return new JSONResponse($psrResponse);
    }

    public function foo(string $id)
    {
        $this->foo = $id . '_';

        return;
    }
}
