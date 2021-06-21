<?php

declare(strict_types=1);

namespace Sigmie;

use Exception;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Http\Connection as Connection;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Index;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\AliasedIndex;
use Sigmie\Base\Index\Builder;
use Sigmie\Http\Contracts\Auth;
use Sigmie\Http\Contracts\JSONRequest as JSONRequestInterface;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;
use Sigmie\Support\Contracts\Collection;

class Sigmie
{
    use IndexActions;

    public function __construct(Connection $httpConnection)
    {
        $this->httpConnection = $httpConnection;
    }

    public function newIndex(string $name): Builder
    {
        $builder = new Index\Builder($this->httpConnection);

        return $builder->alias($name);
    }

    public function index(string $name): AliasedIndex
    {
        return $this->getIndex($name);
    }

    public function indices(): Collection
    {
        return $this->listIndices();
    }

    public function delete(Index\Index $index)
    {
        $this->deleteIndex($index->name());
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

        return new Sigmie(new Connection($client));
    }

    protected function httpCall(JSONRequestInterface $request): ElasticsearchResponse
    {
        return ($this->httpConnection)($request);
    }
}
