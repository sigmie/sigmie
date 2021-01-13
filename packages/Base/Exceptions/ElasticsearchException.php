<?php

declare(strict_types=1);

namespace Sigmie\Base\Exceptions;

use Exception;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Http\Contracts\JsonRequest;

class ElasticsearchException extends Exception
{
    protected JsonRequest $request;

    protected ElasticsearchResponse $response;

    public function __construct(JsonRequest $request, ElasticsearchResponse $response)
    {
        $text = $response->json('error')['type'];
        $text = str_replace('_', ' ', $text);
        $text = ucfirst($text);
        $text .= '.';

        parent::__construct($text, $response->json('status'));

        // dump($request->getBody()->getContents());
        // dump($response->json());

        $this->request = $request;
        $this->response = $response;
    }
}
