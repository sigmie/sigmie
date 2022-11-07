<?php

declare(strict_types=1);

namespace Sigmie;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Analytics\MetricQueryBuilder;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\Contracts\ElasticsearchConnection as Connection;
use Sigmie\Base\Contracts\ElasticsearchRequest as ElasticsearchRequestInterface;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchConnection as HttpConnection;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Document\AliveCollection;
use Sigmie\Http\Contracts\Auth;
use Sigmie\Http\JSONClient;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\NewIndex;
use Sigmie\Query\Aggs;
use Sigmie\Query\Contracts\Aggs as AggsInterface;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Query;
use Sigmie\Search\Search;
use Sigmie\Search\SearchBuilder;

class Sigmie
{
    use IndexActions;
    use Index;

    public function __construct(Connection $httpConnection)
    {
        $this->elasticsearchConnection = $httpConnection;
    }

    public function newIndex(string $name): NewIndex
    {
        $builder = new NewIndex($this->elasticsearchConnection);

        return $builder->alias($name);
    }

    public function index(string $name): null|AliasedIndex
    {
        return $this->getIndex($name);
    }

    public function collect(string $name, bool $refresh = false): AliveCollection
    {
        $aliveIndex = new AliveCollection($name, $this->elasticsearchConnection);

        if ($refresh) {
            return $aliveIndex->refresh();
        }

        return $aliveIndex;
    }

    public function refresh(string $name)
    {
        return $this->indexAPICall("{$name}/_refresh", 'GET');
    }

    public function search(
        string $index,
        Query $query = new MatchAll(),
        AggsInterface $aggs = new Aggs()
    ) {
        $search = new Search($query, $aggs);

        $search->setElasticsearchConnection($this->elasticsearchConnection);

        return $search->index($index);
    }

    public function newSearch(string $index): SearchBuilder
    {
        return new SearchBuilder($index, $this->elasticsearchConnection);
    }

    public function metrics(string $index, string $field)
    {
        return new MetricQueryBuilder($this->newSearch($index), $field);
    }

    public function indices(): array
    {
        return $this->listIndices();
    }

    public function isConnected(): bool
    {
        try {
            $request = new ElasticsearchRequest('GET', new Uri());

            $res = ($this->elasticsearchConnection)($request);

            return ! $res->failed();
        } catch (ConnectException) {
            return false;
        }
    }

    public static function create(array|string $hosts, ?Auth $auth = null): static
    {
        $hosts = (is_string($hosts)) ? explode(',', $hosts) : $hosts;

        $client = JSONClient::create($hosts, $auth);

        return new static(new HttpConnection($client));
    }

    public function delete(string $index): bool
    {
        return $this->deleteIndex($index);
    }

    protected function elasticsearchCall(ElasticsearchRequestInterface $request): ElasticsearchResponse
    {
        return ($this->elasticsearchConnection)($request);
    }
}
