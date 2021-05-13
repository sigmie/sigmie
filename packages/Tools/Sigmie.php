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
use Sigmie\Base\Index\Builder;
use Sigmie\Http\Contracts\Auth;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;
use Sigmie\Http\Contracts\JSONRequest as JSONRequestInterface;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;
use Sigmie\Support\Contracts\Collection;
use Sigmie\Base\Index;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Sigmie implements ManagerInterface
{
    use IndexActions;

    public function __construct(Connection $httpConnection, EventDispatcherInterface $events = null)
    {
        $this->httpConnection = $httpConnection;

        if (is_null($events)) {
            $events = new EventDispatcher;
        }

        $this->events = $events;
    }

    public function newIndex(string $name): Builder
    {
        $builder = new Index\Builder($this->httpConnection, $this->events);

        return $builder->alias($name);
    }

    public function index(string $name): Index\Index
    {
        $index = $this->getIndex($name);

        return $index;
    }

    public function indices(): Collection
    {
        return $this->listIndices();
    }

    public function delete(Index\Index $index)
    {
        $this->deleteIndex($index->getName());
    }

    public function isConnected(): bool
    {
        try {
            $res = ($this->httpConnection)(new JSONRequest('GET', new Uri()));

            return !$res->failed();
        } catch (Exception $e) {
            return false;
        }
    }

    public static function create(string $host, ?Auth $auth = null)
    {
        $client = JSONClient::create($host, $auth);

        return new Sigmie($client);
    }

    protected function httpCall(JSONRequestInterface $request, string $responseClass = ElasticsearchResponse::class): ElasticsearchResponse
    {
        return ($this->httpConnection)($request, $responseClass);
    }
}
