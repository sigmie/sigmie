<?php

namespace Sigmie\Tests\Helpers\Traits;

use GuzzleHttp\Client;

trait NeedsClient
{
    /**
     * @var Client
     */
    private $client = null;

    public function client()
    {
        if ($this->client === null) {
            $this->client = new Client([
                'base_uri'        => 'http://' . getenv('ES_HOST') . ':' . getenv('ES_PORT'),
                'timeout'         => 0,
                'http_errors'     => false,
                'allow_redirects' => false,
            ]);
        }

        return $this->client;
    }
}
