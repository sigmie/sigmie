<?php

declare(strict_types=1);

namespace Sigmie\Search;

use GuzzleHttp\Client;
use Sigmie\Auth\BasicAuth;
use Sigmie\Auth\NoAuthentication;
use Sigmie\Auth\Token;
use Sigmie\Contracts\Authorizer;
use Sigmie\Search\Indices\Service as IndicesService;
use Sigmie\Search\Cluster\Service as ClusterService;

class SigmieClient
{
    private IndicesService $indices;

    private ClusterService $cluster;

    public function __construct(Authorizer $authorizer, string $url)
    {
        $client = new Client([
            'base_uri'        => $url,
            'timeout'         => 0,
            'allow_redirects' => false,
            'headers' => $authorizer->headers()
        ]);

        $this->indices = new IndicesService($client);
        $this->cluster = new ClusterService($client);
    }

    public static function createFromBasicAuth($username, $password, $url)
    {
        return new static(new BasicAuth($username, $password), $url);
    }

    public static function createFromToken(string $token, string $url)
    {
        return new static(new Token($token), $url);
    }

    public static function createWithoutAuth($url)
    {
        return new self(new NoAuthentication(), $url);
    }

    public function indices(): IndicesService
    {
        return $this->indices;
    }

    public function cluster(): ClusterService
    {
        return $this->cluster;
    }
}
