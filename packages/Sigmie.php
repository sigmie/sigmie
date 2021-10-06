<?php

declare(strict_types=1);

namespace Sigmie;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Actions\Index as IndexActions;
use Sigmie\Base\Contracts\ElasticsearchRequest as ElasticsearchRequestInterface;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\HttpConnection as Connection;
use Sigmie\Base\Documents\AliveCollection;
use Sigmie\Base\Http\Connection as HttpConnection;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Index\Builder;
use Sigmie\Http\Contracts\Auth;
use Sigmie\Http\JSONClient;
use Sigmie\Support\Contracts\Collection;
use Sigmie\Base\Index\AliasedIndex;
use Sigmie\Base\Actions\Alias;

class Sigmie
{
    use IndexActions;

    public function __construct(Connection $httpConnection)
    {
        $this->httpConnection = $httpConnection;
    }

    public function newIndex(string $name): Builder
    {
        $builder = new Builder($this->httpConnection);

        return $builder->alias($name);
    }

    public function index(string $name): null|AliasedIndex
    {
        return $this->getIndex($name);
    }

    public function collect(string $name, string $refresh = 'false'): AliveCollection
    {
        $index = new AliveCollection($name, $refresh);

        $index->setHttpConnection($this->httpConnection);

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
