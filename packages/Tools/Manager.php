<?php

declare(strict_types=1);

namespace Sigmie\Base;

use Exception;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\Connection as RequestInterface;
use Sigmie\Base\Contracts\Manager as ManagerInterface;
use Sigmie\Base\Http\Connection as Connection;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;
use Sigmie\Http\Contracts\Auth;
use Sigmie\Http\Contracts\JsonClient as JsonClientInterface;
use Sigmie\Http\Contracts\JsonRequest as JsonRequestInterface;
use Sigmie\Http\JsonClient;
use Sigmie\Http\JsonRequest;
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
        $index->setConnection($this->request);

        return $index;
    }

    public function index(string $name): Index
    {
        $index = $this->getIndex($name);
        $index->setConnection($this->request);

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
            $res = ($this->request)(new JsonRequest('GET', new Uri()));

            return !$res->failed();
        } catch (Exception $e) {
            return false;
        }
    }

    public static function create(string $host, ?Auth $auth = null)
    {
        $client = JsonClient::create($host, $auth);

        return new Manager($client);
    }

    protected function call(JsonRequestInterface $request, string $responseClass = ElasticsearchResponse::class): ElasticsearchResponse
    {
        return ($this->request)($request, $responseClass);
    }
}
