<?php

declare(strict_types=1);

namespace Sigmie;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchConnection as Connection;
use Sigmie\Base\Http\ElasticsearchConnection as HttpConnection;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Document\AliveCollection;
use Sigmie\Http\JSONClient;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Index;
use Sigmie\Index\NewIndex;
use Sigmie\Query\Aggs;
use Sigmie\Query\Contracts\Aggs as AggsInterface;
use Sigmie\Query\NewQuery;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Search;
use Sigmie\Search\ExistingScript;
use Sigmie\Search\NewSearch;
use Sigmie\Search\NewTemplate;

class Sigmie
{
    use IndexActions;

    public function __construct(Connection $httpConnection)
    {
        $this->elasticsearchConnection = $httpConnection;
    }

    public function newIndex(string $name): NewIndex
    {
        $builder = new NewIndex($this->elasticsearchConnection);

        return $builder->alias($name);
    }

    public function index(string $name): null|AliasedIndex|Index
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

    public function query(
        string $index,
        Query $query = new MatchAll(),
        AggsInterface $aggs = new Aggs()
    ) {
        $search = new Search($query, $aggs);

        $search->setElasticsearchConnection($this->elasticsearchConnection);

        return $search->index($index);
    }

    public function newQuery(string $index): NewQuery
    {
        return new NewQuery($this->elasticsearchConnection, $index);
    }

    public function newSearch(string $index): NewSearch
    {
        $search = new NewSearch($this->elasticsearchConnection);

        return $search->index($index);
    }

    public function newTemplate(string $id): NewTemplate
    {
        $builder = new NewTemplate(
            $this->elasticsearchConnection,
        );

        return $builder->id($id);
    }

    public function template(string $id): ExistingScript
    {
        return new ExistingScript($id, $this->elasticsearchConnection);
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

    public static function create(array|string $hosts, array $config = []): static
    {
        $hosts = (is_string($hosts)) ? explode(',', $hosts) : $hosts;

        $client = JSONClient::create($hosts, $config);

        return new static(new HttpConnection($client));
    }

    public function delete(string $index): bool
    {
        return $this->deleteIndex($index);
    }
}
