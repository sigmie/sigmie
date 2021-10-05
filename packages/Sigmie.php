<?php

declare(strict_types=1);

namespace Sigmie;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchRequest as ElasticsearchRequestInterface;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\HttpConnection as Connection;
use Sigmie\Base\Http\Connection as HttpConnection;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Index;
use Sigmie\Base\Index\IndexActions as IndexActions;
use Sigmie\Base\Index\Builder;
use Sigmie\Base\Index\CollectedIndex;
use Sigmie\Base\Index\AbstractIndex as IndexIndex;
use Sigmie\Base\Index\ActiveIndex;
use Sigmie\Base\Index\PaginatedIndex;
use Sigmie\Http\Contracts\Auth;
use Sigmie\Http\JSONClient;
use Sigmie\Support\Contracts\Collection;
use Sigmie\Base\Index\AliasedIndex;

class Sigmie
{
    public function __construct(Connection $httpConnection)
    {
        $this->httpConnection = $httpConnection;
    }

    public function newIndex(string $name): Builder
    {
        $builder = new Index\Builder($this->httpConnection);

        return $builder->alias($name);
    }

    public function paginate(string $name): PaginatedIndex
    {
        $index = new PaginatedIndex($name);

        $index->setHttpConnection(
            $this->httpConnection
        );

        return $index;
    }

    public function index(string $name): ActiveIndex
    {
        $index = new ActiveIndex($name);

        $index->setHttpConnection(
            $this->httpConnection
        );

        return $index;
    }

    public function collect(string $name): CollectedIndex
    {
        $index = new CollectedIndex($name);

        $index->setHttpConnection(
            $this->httpConnection
        );

        return $index;
    }

    public function indices(): Collection
    {
        return $this->listIndices();
    }

    public function isConnected(): bool
    {
        try {
            $request = new ElasticsearchRequest('GET', new Uri());

            $res = ($this->httpConnection)($request);

            return !$res->failed();
        } catch (ConnectException) {
            return false;
        }
    }

    public static function create(string $host, ?Auth $auth = null): static
    {
        $client = JSONClient::create($host, $auth);

        return new static(new HttpConnection($client));
    }

    protected function httpCall(ElasticsearchRequestInterface $request): ElasticsearchResponse
    {
        return ($this->httpConnection)($request);
    }
}
