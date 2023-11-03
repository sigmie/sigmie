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

    protected string $application = '';

    public function __construct(Connection $httpConnection)
    {
        $this->elasticsearchConnection = $httpConnection;
    }

    private function withApplicationPrefix(string $name): string
    {
        if ($this->application === '') {
            return $name;
        }

        return $this->application . '-' . $name;
    }

    public function application(string $application)
    {
        $this->application = $application;

        return $this;
    }

    public function newIndex(string $name): NewIndex
    {
        $builder = new NewIndex($this->elasticsearchConnection);

        return $builder->alias($this->withApplicationPrefix($name));
    }

    public function index(string $name): null|AliasedIndex|Index
    {
        return $this->getIndex($this->withApplicationPrefix($name));
    }

    public function collect(string $name, bool $refresh = false): AliveCollection
    {
        $aliveIndex = new AliveCollection($this->withApplicationPrefix($name), $this->elasticsearchConnection, $refresh ? 'true' : 'false');

        return $aliveIndex;
    }

    public function query(
        string $index,
        Query $query = new MatchAll(),
        AggsInterface $aggs = new Aggs()
    ) {
        $search = new Search($query, $aggs);

        $search->setElasticsearchConnection($this->elasticsearchConnection);

        return $search->index($this->withApplicationPrefix($index));
    }

    public function newQuery(string $index): NewQuery
    {
        $index = $this->withApplicationPrefix($index);

        return new NewQuery($this->elasticsearchConnection, $index);
    }

    public function newSearch(string $index): NewSearch
    {
        $index = $this->withApplicationPrefix($index);

        $search = new NewSearch($this->elasticsearchConnection);

        return $search->index($index);
    }

    public function newTemplate(string $id): NewTemplate
    {
        $id = $this->withApplicationPrefix($id);

        $builder = new NewTemplate(
            $this->elasticsearchConnection,
        );

        return $builder->id($id);
    }

    public function refresh(string $indexName)
    {
        $this->refreshIndex($indexName);
    }

    public function template(string $id): ExistingScript
    {
        $id = $this->withApplicationPrefix($id);

        return new ExistingScript($id, $this->elasticsearchConnection);
    }

    public function indices(string $pattern = '*'): array
    {
        $pattern = $this->withApplicationPrefix($pattern);

        return $this->listIndices($pattern);
    }

    public function isConnected(): bool
    {
        try {
            $request = new ElasticsearchRequest('GET', new Uri());

            $res = ($this->elasticsearchConnection)($request);

            return !$res->failed();
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
        if (!str_starts_with($index, $this->application)) {
            $index = $this->withApplicationPrefix($index);
        }

        return $this->deleteIndex($index);
    }
}
