<?php

declare(strict_types=1);

namespace Sigmie\Tools;

use Exception;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\HttpConnection as RequestInterface;
use Sigmie\Base\Contracts\Manager as ManagerInterface;
use Sigmie\Base\Http\Connection as Connection;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;
use Sigmie\Http\Contracts\Auth;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;
use Sigmie\Http\Contracts\JSONRequest as JSONRequestInterface;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;
use Sigmie\Support\Contracts\Collection;

class Manager implements ManagerInterface
{
    use IndexActions;

    private RequestInterface $request;

    public function __construct(JsonClientInterface $http)
    {
        $this->request = new Connection($http);
    }

    public function newIndex(string $name): Index
    {
        $index = $this->createIndex(new Index($name));
        $index->setHttpConnection($this->request);

        return $index;
    }

    public function index(string $name): Index
    {
        $index = $this->getIndex($name);
        $index->setHttpConnection($this->request);

        return $index;
    }

    public function indices(): Collection
    {
        return $this->listIndices();
    }

    public function delete(Index $index)
    {
        $this->deleteIndex($index->getName());
    }

    public function isConnected(): bool
    {
        try {
            $res = ($this->request)(new JSONRequest('GET', new Uri()));

            return !$res->failed();
        } catch (Exception $e) {
            return false;
        }
    }

    public static function create(string $host, ?Auth $auth = null)
    {
        $client = JSONClient::create($host, $auth);

        return new Manager($client);
    }

    protected function httpCall(JSONRequestInterface $request, string $responseClass = ElasticsearchResponse::class): ElasticsearchResponse
    {
        return ($this->request)($request, $responseClass);
    }
}
