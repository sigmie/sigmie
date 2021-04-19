<?php

declare(strict_types=1);

namespace App\Helpers;

use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;

final class ProxyRequestResponse
{
    public function __construct(
        private Request $request,
        private Response $response
    ) {
    }

    public function __invoke()
    {
        return [
            'request' => [
                'content' => json_decode($this->request->getContent(), true)
            ],
            'response' => [
                'content' => json_decode($this->response->getContent(), true),
            ]
        ];
    }
}
