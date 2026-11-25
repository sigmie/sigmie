<?php

declare(strict_types=1);

namespace Sigmie;

use DateTimeInterface;
use Elastic\Transport\Exception\CloudIdParseException;
use Elastic\Transport\TransportBuilder;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use Http\Adapter\Guzzle7\Client as AsyncClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Analytics\Analytics;
use Sigmie\Base\APIs\Search as APIsSearch;
use Sigmie\Base\Contracts\ElasticsearchConnection as Connection;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\Drivers\Opensearch;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchConnection as HttpConnection;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Http\TransportClient;
use Sigmie\Classification\NewClassification;
use Sigmie\Contracts\Package;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Contracts\CollectionHook;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Index;
use Sigmie\Index\IndexRebuild;
use Sigmie\Index\ListedIndex;
use Sigmie\Index\NewIndex;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\Aggs;
use Sigmie\Query\Contracts\Aggs as AggsInterface;
use Sigmie\Query\NewQuery;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Search;
use Sigmie\Search\ExistingScript;
use Sigmie\Search\NewMultiSearch;
use Sigmie\Search\NewRecommendations;
use Sigmie\Search\NewSearch;
use Sigmie\Search\NewTemplate;
use Throwable;

class Sigmie
{
    use APIsSearch;
    use IndexActions;

    public const DATE_FORMAT = 'Y-m-d H:i:s.u';

    protected string $application = '';

    protected array $apis = [];

    public static array $plugins = [];

    /** @var array<int, CollectionHook> */
    protected array $collectionHooks = [];

    public function __construct(
        Connection $httpConnection
    ) {
        $this->elasticsearchConnection = $httpConnection;
    }

    public function registerApi(string $name, EmbeddingsApi|RerankApi $api): static
    {
        $this->apis[$name] = $api;

        return $this;
    }

    public function api(string $name): EmbeddingsApi|RerankApi
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
            ->alias($name)
            ->apis($this->apis)
            ->collectionHooks($this->collectionHooks);
    }

    /**
     * Resume the pending rebuild of an alias from any process, e.g. a queued
     * job filling one chunk. Returns the newest unattached index started by
     * NewIndex::startRebuild() for this alias, or null when none is pending.
     */
    public function rebuilding(string $alias): ?IndexRebuild
    {
        $pending = array_filter(
            $this->indexAPICall(NewIndex::physicalNamePrefix($alias).'_*?expand_wildcards=open,hidden', 'GET')->json() ?? [],
            fn (array $index, string $name): bool => isset($index['aliases'][IndexRebuild::pendingAlias($name)])
                && ($index['mappings']['_meta'][IndexRebuild::META_KEY] ?? null) === $alias,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($pending === []) {
            return null;
        }

        // Older names contain local wall times, which can run backward at a DST change.
        uksort($pending, static function (string $left, string $right) use ($pending): int {
            $leftCreated = $pending[$left]['settings']['index']['creation_date'] ?? 0;
            $rightCreated = $pending[$right]['settings']['index']['creation_date'] ?? 0;

            return ($rightCreated <=> $leftCreated) ?: strcmp($right, $left);
        });

        $name = (string) array_key_first($pending);
        $meta = $pending[$name]['mappings']['_meta'] ?? [];
        $replicas = array_key_exists(IndexRebuild::META_REPLICAS, $meta) && $meta[IndexRebuild::META_REPLICAS] === null
            ? null
            : (int) ($meta[IndexRebuild::META_REPLICAS] ?? 0);
        $targets = $meta[IndexRebuild::META_TARGETS] ?? null;
        $targets = is_array($targets)
            ? array_map(static fn (int|string $target): string => (string) $target, array_values($targets))
            : null;

        return (new IndexRebuild(
            $this->elasticsearchConnection, $alias, $name, $replicas, $this->collectionHooks, $targets,
            deferredBlocks: $meta[IndexRebuild::META_BLOCKS] ?? [],
            fillRouting: $pending[$name]['aliases'][IndexRebuild::pendingAlias($name)]['index_routing'] ?? null,
        ))
            ->apis($this->apis);
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
        ))->apis($this->apis)->hooks($this->collectionHooks);
    }

    public function extend(Package $package): void
    {
        $package->register($this);
    }

    public function addCollectionHook(CollectionHook $hook): void
    {
        $this->collectionHooks[] = $hook;
    }

    public function newClassification(EmbeddingsApi $embeddingsApi): NewClassification
    {
        return new NewClassification($embeddingsApi);
    }

    public function newRecommend(string $index): NewRecommendations
    {
        return new NewRecommendations($index, $this->elasticsearchConnection);
    }

    public function rawQuery(
        string $index,
        array $query
    ): Base\Http\Responses\Search {
        return $this->searchAPICall($index, Search::normalizeBody($query));
    }

    public function query(
        string $index,
        ?Query $query = null,
        AggsInterface $aggs = new Aggs
    ): Search {
        $search = new Search($this->elasticsearchConnection);

        if ($query instanceof Query) {
            $search->query($query);
        }

        return $search->aggs($aggs)->index($index);
    }

    public function newQuery(string $index): NewQuery
    {
        return new NewQuery($this->elasticsearchConnection, $index);
    }

    /**
     * Dashboard analytics over a timeline (date) field of an index. Lower-level sibling of
     * {@see SigmieIndex::analytics()}: pass `->properties()` so the filter DSL is typed and
     * keyword fields resolve to their `.keyword` aggregatable path.
     */
    public function analytics(
        string $index,
        string $dateField,
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
    ): Analytics {
        return new Analytics($this->newQuery($index), new NewProperties, $dateField, $from, $to);
    }

    public function newSearch(string $index): NewSearch
    {
        return (new NewSearch($this->elasticsearchConnection))
            ->index($index)
            ->apis($this->apis);
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
            $request = new ElasticsearchRequest('GET', new Uri);
            $res = ($this->elasticsearchConnection)($request);

            return ! $res->failed();
        } catch (Throwable) {
            return false;
        }
    }

    public function isServerless(): bool
    {
        return $this->elasticsearchConnection->isServerless();
    }

    public static function createForCloud(
        string $cloudId,
        string $apiKey
    ): static {
        return static::create(self::elasticsearchHostFromCloudId($cloudId), config: [
            'headers' => ['Authorization' => 'ApiKey '.$apiKey],
        ]);
    }

    public static function createForServerless(
        string $host,
        string $apiKey
    ): static {
        return new static(self::connection([$host], SearchEngineType::Elasticsearch, [
            'headers' => ['Authorization' => 'ApiKey '.$apiKey],
        ], serverless: true));
    }

    public static function create(
        array|string $hosts,
        SearchEngineType $engine = SearchEngineType::Elasticsearch,
        array $config = []
    ): static {
        $hosts = is_string($hosts) ? array_map('trim', explode(',', $hosts)) : $hosts;

        return new static(self::connection($hosts, $engine, $config));
    }

    private static function connection(array $hosts, SearchEngineType $engine, array $config, bool $serverless = false): HttpConnection
    {
        $networkError = self::networkErrorHolder();
        $config = [
            'allow_redirects' => false,
            'http_errors' => false,
            'connect_timeout' => 15,
            ...self::captureNetworkError($config, $networkError),
        ];
        $hosts = array_map(static fn (string $host): string => str_contains($host, '://')
            ? (string) new Uri($host)
            : $host, $hosts);
        $guzzle = new GuzzleHttpClient($config);
        // Guzzle's PSR-18 sendRequest forces allow_redirects and http_errors
        // false, so those two config values never replace the defaults.
        $transport = TransportBuilder::create()
            ->setHosts($hosts)
            ->setClient(new class($guzzle) implements ClientInterface
            {
                public function __construct(private GuzzleHttpClient $guzzle) {}

                public function sendRequest(RequestInterface $request): ResponseInterface
                {
                    return $this->guzzle->send($request);
                }
            })
            ->build()
            ->setAsyncClient(new AsyncClient($config['http_errors']
                ? new GuzzleHttpClient([...$config, 'http_errors' => false])
                : $guzzle));
        $client = new TransportClient($transport, $config['http_errors']);

        $driver = match ($engine) {
            SearchEngineType::Elasticsearch => new Elasticsearch,
            SearchEngineType::OpenSearch => new Opensearch,
        };

        return new HttpConnection($client, $driver, $serverless, $networkError);
    }

    private static function elasticsearchHostFromCloudId(string $cloudId): string
    {
        $encoded = explode(':', $cloudId, 2)[1] ?? '';
        $decoded = base64_decode($encoded, true);
        $segments = is_string($decoded) ? explode('$', $decoded) : [];

        if ($encoded === '' || ! is_string($decoded) || count($segments) < 2 || $segments[0] === '' || $segments[1] === '') {
            throw new CloudIdParseException('Cloud ID not valid');
        }

        [$domain, $domainPort] = array_pad(explode(':', $segments[0], 2), 2, null);
        [$esId, $esPort] = array_pad(explode(':', $segments[1], 2), 2, null);
        $port = ($esPort !== null && $esPort !== '') ? $esPort : $domainPort;
        $host = sprintf('https://%s.%s', $esId, $domain);

        // The Cloud ID stores the port after the Elasticsearch id, or on the
        // domain. setCloudId() drops that port, and HTTPS then uses 443.
        return ($port === null || $port === '') ? $host : $host.':'.$port;
    }

    private static function networkErrorHolder(): object
    {
        return new class
        {
            public ?Throwable $error = null;
        };
    }

    private static function captureNetworkError(array $config, object $captured): array
    {
        $handler = $config['handler'] ?? null;
        $stack = $handler instanceof HandlerStack
            ? clone $handler
            : HandlerStack::create(is_callable($handler) ? $handler : null);

        $stack->push(static fn (callable $next): callable => static function ($request, array $options) use ($next, $captured) {
            if (! ($options['synchronous'] ?? false)) {
                return $next($request, $options);
            }

            return $next($request, $options)->then(null, static function ($reason) use ($captured): PromiseInterface {
                if ($reason instanceof NetworkExceptionInterface) {
                    $captured->error = $reason;
                }

                return Create::rejectionFor($reason);
            });
        });

        $config['handler'] = $stack;

        return $config;
    }

    public function delete(string $index): bool
    {
        $indexNames = array_map('trim', explode(',', $index));
        $acknowledged = true;

        foreach ($this->listIndices(implode(',', $indexNames)) as $listedIndex) {
            if (in_array($listedIndex->name, $indexNames, true) || array_intersect($indexNames, $listedIndex->aliases) !== []) {
                $acknowledged = $this->deleteIndex($listedIndex->name) && $acknowledged;
            }
        }

        return $acknowledged;
    }

    public function deleteIfExists(string $index): bool
    {
        $indexNames = array_map('trim', explode(',', $index));
        $acknowledged = true;

        foreach ($indexNames as $indexName) {
            try {
                $indices = $this->listIndices($indexName);

                /** @var ListedIndex $listedIndex */
                foreach ($indices as $listedIndex) {
                    if ($listedIndex->name === $indexName || in_array($indexName, $listedIndex->aliases)) {
                        try {
                            $acknowledged = $this->deleteIndex($listedIndex->name) && $acknowledged;
                            // @codeCoverageIgnoreStart
                        } catch (ElasticsearchException $e) {
                            if ($e->json('type') !== 'index_not_found_exception') {
                                throw $e;
                            }
                        }

                        // @codeCoverageIgnoreEnd
                    }
                }

                // @codeCoverageIgnoreStart
            } catch (ElasticsearchException $e) {
                if ($e->json('type') !== 'index_not_found_exception') {
                    throw $e;
                }
            }

            // @codeCoverageIgnoreEnd
        }

        return $acknowledged;
    }

    public static function registerPlugins(array|string $plugins): void
    {
        self::$plugins = [...self::$plugins, ...array_values((array) $plugins)];
    }

    public static function isPluginRegistered(string $plugin): bool
    {
        return in_array($plugin, self::$plugins, true);
    }
}
