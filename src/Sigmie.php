<?php

declare(strict_types=1);

namespace Sigmie;

use Sigmie\Base\ElasticsearchException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Uri;
use Sigmie\AI\Contracts\LLMApi;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Base\APIs\Search as APIsSearch;
use Sigmie\Base\Contracts\ElasticsearchConnection as Connection;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\Drivers\Opensearch;
use Sigmie\Base\Http\ElasticsearchConnection as HttpConnection;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Document\AliveCollection;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Http\JSONClient;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Index;
use Sigmie\Index\ListedIndex;
use Sigmie\Index\NewIndex;
use Sigmie\Query\Aggs;
use Sigmie\Query\Contracts\Aggs as AggsInterface;
use Sigmie\Query\NewQuery;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Search;
use Sigmie\Search\ExistingScript;
use Sigmie\Search\NewMultiSearch;
use Sigmie\Search\NewRag;
use Sigmie\Search\NewSearch;
use Sigmie\Search\NewTemplate;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Classification\NewClassification;
use Sigmie\Clustering\NewClustering;
use Sigmie\Search\NewRecommendations;

class Sigmie
{
    use IndexActions;
    use APIsSearch;

    public const DATE_FORMAT = 'Y-m-d H:i:s.u';

    protected string $application = '';

    protected array $apis = [];

    public static array $plugins = [];

    public function __construct(
        Connection $httpConnection
    ) {
        $this->elasticsearchConnection = $httpConnection;
    }

    public function registerApi(string $name, EmbeddingsApi|LLMApi|RerankApi $api): static
    {
        $this->apis[$name] = $api;

        return $this;
    }

    public function api(string $name): EmbeddingsApi|LLMApi|RerankApi
    {
        return $this->apis[$name];
    }

    public function hasApi(string $name): bool
    {
        return isset($this->apis[$name]);
    }

    public function application(string $application): static
    {
        $this->application = $application;

        return $this;
    }

    public function newIndex(string $name): NewIndex
    {
        return (new NewIndex($this->elasticsearchConnection))
            ->alias($name);
    }

    public function index(string $name): null|AliasedIndex|Index
    {
        return $this->getIndex($name);
    }

    public function indexUpsert(string $name, callable $builder): AliasedIndex
    {
        $existingIndex = $this->index($name);

        if ($existingIndex instanceof AliasedIndex) {
            return $existingIndex->update($builder);
        }

        $newIndex = $this->newIndex($name);
        $newIndex = $builder($newIndex);

        return $newIndex->create();
    }

    public function collect(string $name, bool $refresh = false): AliveCollection
    {
        return (new AliveCollection(
            $name,
            $this->elasticsearchConnection,
            $refresh ? 'true' : 'false'
        ))->apis($this->apis);
    }

    public function newClassification(EmbeddingsApi $embeddingsApi): NewClassification
    {
        return new NewClassification($embeddingsApi);
    }

    public function newClustering(EmbeddingsApi $embeddingsApi): NewClustering
    {
        return new NewClustering($embeddingsApi);
    }

    public function newRecommend(string $index): NewRecommendations
    {
        return new NewRecommendations($index, $this->elasticsearchConnection);
    }

    public function rawQuery(
        string $index,
        array $query
    ): \Sigmie\Base\Http\Responses\Search {
        return $this->searchAPICall($index, $query);
    }

    public function query(
        string $index,
        Query $query = new MatchAll(),
        AggsInterface $aggs = new Aggs()
    ): \Sigmie\Query\Search {
        $search = new Search($this->elasticsearchConnection);

        $search = $search->query($query)->aggs($aggs);

        return $search->index($index);
    }

    public function newQuery(string $index): NewQuery
    {
        return new NewQuery($this->elasticsearchConnection, $index);
    }

    public function newSearch(string $index): NewSearch
    {
        return (new NewSearch($this->elasticsearchConnection))
            ->index($index)
            ->apis($this->apis);
    }

    public function newRag(
        LLMApi $llm,
        ?RerankApi $reranker = null
    ): NewRag {

        return new NewRag($llm, $reranker);
    }

    public function newMultiSearch(): NewMultiSearch
    {
        return (new NewMultiSearch($this->elasticsearchConnection))
            ->apis($this->apis);
    }

    public function newTemplate(string $id): NewTemplate
    {
        return (new NewTemplate($this->elasticsearchConnection))
            ->id($id);
    }

    public function refresh(string $indexName): void
    {
        $this->refreshIndex($indexName);
    }

    public function template(string $id): ExistingScript
    {
        return new ExistingScript($id, $this->elasticsearchConnection);
    }

    public function indices(string $pattern = '*'): array
    {
        return $this->listIndices($pattern);
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

    public static function create(
        array|string $hosts,
        SearchEngineType $engine = SearchEngineType::Elasticsearch,
        array $config = []
    ): static {
        $hosts = (is_string($hosts)) ? explode(',', $hosts) : $hosts;

        $client = JSONClient::create($hosts, $config);

        $driver = match ($engine) {
            SearchEngineType::Elasticsearch => new Elasticsearch(),
            SearchEngineType::OpenSearch => new Opensearch(),
        };

        return new static(new HttpConnection($client, $driver));
    }

    public function delete(string $index): bool
    {
        $indexNames = array_map('trim', explode(',', $index));

        foreach ($indexNames as $indexName) {
            $indices = $this->listIndices($indexName);

            /** @var ListedIndex $listedIndex */
            foreach ($indices as $listedIndex) {
                // Check if the index name matches or if any of its aliases match
                if ($listedIndex->name === $indexName || in_array($indexName, $listedIndex->aliases)) {
                    $this->deleteIndex($listedIndex->name);
                }
            }
        }

        return true;
    }

    public function deleteIfExists(string $index): bool
    {
        $indexNames = array_map('trim', explode(',', $index));

        foreach ($indexNames as $indexName) {
            try {
                $indices = $this->listIndices($indexName);

                /** @var ListedIndex $listedIndex */
                foreach ($indices as $listedIndex) {
                    if ($listedIndex->name === $indexName || in_array($indexName, $listedIndex->aliases)) {
                        try {
                            $this->deleteIndex($listedIndex->name);
                        } catch (ElasticsearchException $e) {
                            if ($e->json('type') !== 'index_not_found_exception') {
                                throw $e;
                            }
                        }
                    }
                }
            } catch (ElasticsearchException $e) {
                if ($e->json('type') !== 'index_not_found_exception') {
                    throw $e;
                }
            }
        }

        return true;
    }

    public static function registerPlugins(array|string $plugins): void
    {
        self::$plugins = array_merge(self::$plugins, (array) $plugins);
    }

    public static function isPluginRegistered(string $plugin): bool
    {
        return in_array($plugin, self::$plugins);
    }
}
