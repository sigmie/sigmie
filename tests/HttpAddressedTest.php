<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Carbon\Carbon;
use Elastic\Transport\Exception\CloudIdParseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Elastic\Transport\NodePool\NodePoolInterface;
use Elastic\Transport\TransportBuilder;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Http\Client\Exception\HttpException;
use Http\Client\Exception\RequestException;
use Http\Client\HttpAsyncClient;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Sigmie\Base\APIs\Analyze;
use Sigmie\Base\APIs\Bulk;
use Sigmie\Base\APIs\Cat;
use Sigmie\Base\APIs\Cluster;
use Sigmie\Base\APIs\Count;
use Sigmie\Base\APIs\Delete;
use Sigmie\Base\APIs\Doc;
use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index as IndexApi;
use Sigmie\Base\APIs\Ingest;
use Sigmie\Base\APIs\Mget as MgetApi;
use Sigmie\Base\APIs\Script;
use Sigmie\Base\APIs\Search as SearchApi;
use Sigmie\Base\APIs\SearchTemplate;
use Sigmie\Base\APIs\Stats;
use Sigmie\Base\APIs\Tasks;
use Sigmie\Base\APIs\Template;
use Sigmie\Base\APIs\Update;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\Drivers\Opensearch;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchConnection as HttpConnection;
use Sigmie\Base\Http\ElasticsearchRequest as HttpRequest;
use Sigmie\Base\Http\ElasticsearchResponse as Response;
use Sigmie\Base\Http\PointInTimeRequests;
use Sigmie\Base\Http\Requests\Bulk as BulkRequest;
use Sigmie\Base\Http\Requests\MSearch as MSearchRequest;
use Sigmie\Base\Http\Responses\Mget;
use Sigmie\Base\Http\Responses\Search;
use Sigmie\Base\Http\TransportClient;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Http\Contracts\JSONClient;
use Sigmie\Http\JSONResponse;
use Sigmie\Index\AliasedIndex;
use Sigmie\Search\Autocomplete\NewPipeline;
use Sigmie\Search\ExistingScript;
use Sigmie\Search\NewSearch;
use Sigmie\Sigmie;
use Sigmie\Testing\Assert as IndexAssert;
use Sigmie\Testing\ClearElasticsearch;
use Sigmie\Testing\ParallelRunner;
use Sigmie\Testing\TestCase as SigmieTestCase;
use Symfony\Component\Process\Process;

class HttpAddressedTest extends TestCase
{
    public function test_multi_get_and_bulk_accept_a_generic_connection_response(): void
    {
        $connection = $this->createStub(ElasticsearchConnection::class);
        $connection->method('__invoke')->willReturnOnConsecutiveCalls(
            new Response(200, [], '{"docs":[]}'),
            new Response(200, [], '{"errors":false,"items":[]}'),
        );
        $api = new class
        {
            use Bulk { bulkAPICall as public; }
            use MgetApi;
        };
        $api->setElasticsearchConnection($connection);

        $this->assertSame([], $api->mgetAPICall('movies', ['ids' => []])->docs());
        $this->assertSame([], $api->bulkAPICall('movies', [])->items());
    }

    public function test_exception_reads_an_object_from_a_document_source(): void
    {
        $body = '{"timed_out":true,"hits":{"hits":[{"_id":"1","_source":{"settings":{}}}]}}';
        $error = (new Search(200, [], $body))->exception(new HttpRequest('POST', new Uri('/movies/_search')));

        $this->assertSame([], $error->json('json.hits.hits.0._source.settings'));
    }

    public function test_request_inspection_preserves_unsigned_long_digits(): void
    {
        $json = '{"doc":{"value":18446744073709551615}}';
        $request = (new HttpRequest('POST', new Uri('/movies/_update/1')))->withBody(Utils::streamFor($json));
        $this->assertSame('18446744073709551615', $request->body()['doc']['value']);

        $bulk = (new BulkRequest('POST', new Uri('/_bulk'), []))->withBody(Utils::streamFor($json."\n"));
        $this->assertSame('18446744073709551615', $bulk->body()[0]['doc']['value']);
    }

    public function test_bulk_errors_filtered_to_item_reasons_raise_the_item_error(): void
    {
        $request = new BulkRequest('POST', new Uri('/_bulk?filter_path=items.*.error'), []);
        $response = $request->response(new PsrResponse(200, [], '{"items":[{"create":{"error":{"type":"version_conflict_engine_exception"}}}]}'));

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('version_conflict_engine_exception');
        $response->assertItemsSucceeded();
    }

    public function test_bulk_with_only_the_error_flag_rejects_failed_writes(): void
    {
        $request = new BulkRequest('POST', new Uri('/_bulk?filter_path=errors'), []);
        $response = $request->response(new PsrResponse(200, [], '{"errors":true}'));

        $this->expectException(ElasticsearchException::class);
        $response->assertItemsSucceeded();
    }

    public function test_index_creation_reports_active_shard_timeout(): void
    {
        $sigmie = $this->withResponse(['acknowledged' => true, 'shards_acknowledged' => false]);

        $this->expectException(ElasticsearchException::class);
        $sigmie->indexAPICall('movies?wait_for_active_shards=all', 'PUT');
    }

    public function test_json_search_rejects_a_truncated_success_response(): void
    {
        $sigmie = Sigmie::create('localhost:9200', config: [
            'handler' => new MockHandler([new PsrResponse(200, ['Content-Type' => 'application/json'], '{"hits":')]),
        ]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('invalid_json_response');
        $sigmie->rawQuery('movies', []);
    }

    public function test_search_reads_an_encoded_gzip_response(): void
    {
        $sigmie = Sigmie::create('localhost:9200', config: [
            'decode_content' => false,
            'handler' => new MockHandler([new PsrResponse(200, ['Content-Encoding' => 'gzip'],
                gzencode('{"hits":{"total":1,"hits":[{"_id":"one"}]}}'))]),
        ]);
        $this->assertSame(1, $sigmie->rawQuery('movies', [])->total());
    }

    public function test_streamed_error_preserves_its_original_response_body(): void
    {
        $json = '{"error":"missing"}';
        $response = Response::fromPsrResponse(new PsrResponse(404, [], new NoSeekStream(Utils::streamFor($json))));
        $error = $response->exception(new HttpRequest('GET', new Uri('/movies')));

        $this->assertSame($json, $error->json('body'));
    }

    public function test_multi_get_keeps_sources_when_found_is_filtered_out(): void
    {
        $sigmie = $this->withResponse(['docs' => [['_source' => ['title' => 'Alien']]]]);
        $response = $sigmie->collect('movies')->mgetAPICall('movies', ['ids' => ['one']], ['filter_path' => 'docs._source']);

        $this->assertSame('Alien', $response->first()?->get('title'));
    }

    public function test_async_network_failure_retires_the_failed_node(): void
    {
        $failure = new ConnectException('Connection refused', new Request('GET', 'http://localhost:9200'));
        $handler = new MockHandler([$failure, new PsrResponse(200, [], '{}')]);
        $client = Sigmie::create('localhost:9200', config: ['handler' => $handler]);

        $promise = $client->query('movies')->promise();
        $promise->wait(false);

        $this->assertSame(Promise::REJECTED, $promise->getState());
        $this->assertFalse($client->isConnected());
        $this->assertCount(1, $handler);
        try {
            $promise->wait();
            $this->fail('Expected a network error.');
        } catch (NoNodeAvailableException $noNodeAvailableException) {
            $this->assertSame($failure, $noNodeAvailableException->getPrevious());
        }
    }

    public function test_async_transfer_error_preserves_its_cause_and_keeps_the_node(): void
    {
        $failure = new GuzzleRequestException('Transfer interrupted', new Request('GET', 'http://localhost:9200'));
        $client = Sigmie::create('localhost:9200', config: [
            'handler' => new MockHandler([$failure, new PsrResponse(200, [], '{}')]),
        ]);

        try {
            $client->query('movies')->promise()->wait();
            $this->fail('Expected a transfer failure.');
        } catch (RequestException $requestException) {
            $this->assertSame($failure, $requestException->getPrevious());
        }

        $this->assertTrue($client->isConnected());
    }

    public function test_count_accepts_shard_metadata_filtered_to_total(): void
    {
        $sigmie = $this->withResponse(['count' => 3, '_shards' => ['total' => 1]]);
        $this->assertSame(3, $sigmie->collect('movies')->count());
    }

    public function test_stored_script_post_checks_acknowledgement(): void
    {
        $api = new class
        {
            use Script { scriptAPICall as public; }
        };
        $api->setElasticsearchConnection($this->withResponse(['acknowledged' => false])->getElasticsearchConnection());

        $this->expectException(ElasticsearchException::class);
        $api->scriptAPICall('POST', 'saved', []);
    }

    public function test_lowercase_ingest_put_checks_acknowledgement(): void
    {
        $api = new class
        {
            use Ingest { ingestAPICall as public; }
        };
        $api->setElasticsearchConnection($this->withResponse(['acknowledged' => false])->getElasticsearchConnection());

        $this->expectException(ElasticsearchException::class);
        $api->ingestAPICall('pipeline', 'put', []);
    }

    public function test_async_http_exception_identifies_the_dispatched_uri(): void
    {
        $sigmie = Sigmie::create('https://localhost:9243/proxy', config: [
            'http_errors' => true,
            'handler' => new MockHandler([new PsrResponse(403)]),
        ]);
        try {
            $sigmie->query('movies')->promise()->wait();
            $this->fail('Expected HTTP 403.');
        } catch (HttpException $httpException) {
            $this->assertSame('https://localhost:9243/proxy/movies/_search', (string) $httpException->getRequest()->getUri());
        }
    }

    public function test_cluster_state_encodes_literal_percent_in_index_names(): void
    {
        $handler = new MockHandler([new PsrResponse(200, [], '{}')]);
        $client = new class
        {
            use Cluster { clusterAPICall as public; }
        };
        $client->setElasticsearchConnection(Sigmie::create('localhost:9200', config: ['handler' => $handler])->getElasticsearchConnection());
        $client->clusterAPICall('state/metadata/logs%20archive?local=true');

        $this->assertSame('/_cluster/state/metadata/logs%2520archive?local=true', $handler->getLastRequest()->getRequestTarget());
    }

    public function test_ndjson_preserves_numeric_field_names_and_empty_objects(): void
    {
        $bulk = new BulkRequest('POST', new Uri('/movies/_bulk'), [
            ['index' => (object) []], [0 => 'value'],
        ]);
        $this->assertSame("{\"index\":{}}\n{\"0\":\"value\"}\n", (string) $bulk->getBody());
        $search = new MSearchRequest('POST', new Uri('/_msearch'), [[], []]);
        $this->assertSame("{}\n{}\n", (string) $search->getBody());
    }

    public function test_bulk_empty_action_metadata_is_an_object(): void
    {
        $bulk = new BulkRequest('POST', new Uri('/movies/_bulk'), [
            ['delete' => ['_id' => 'old']], ['create' => []], ['create' => []],
        ]);
        $this->assertSame("{\"delete\":{\"_id\":\"old\"}}\n{\"create\":{}}\n{\"create\":[]}\n", (string) $bulk->getBody());
    }

    public function test_async_search_hydrates_a_generic_connection_response(): void
    {
        $connection = $this->createStub(ElasticsearchConnection::class);
        $connection->method('promise')->willReturn(new FulfilledPromise(new Response(200, [], '{"hits":{"total":3}}')));

        $this->assertSame(3, (new Sigmie($connection))->query('movies')->promise()->wait()->total());
    }

    public function test_search_rejects_a_partial_generic_connection_response(): void
    {
        $connection = $this->createStub(ElasticsearchConnection::class);
        $connection->method('__invoke')->willReturn(new Response(200, [], '{"timed_out":true}'));

        $this->expectException(ElasticsearchException::class);
        (new Sigmie($connection))->rawQuery('movies', []);
    }

    public function test_a_rollover_with_unmet_conditions_returns_its_status(): void
    {
        $status = [
            'acknowledged' => false,
            'shards_acknowledged' => false,
            'old_index' => 'movies-000001',
            'new_index' => 'movies-000002',
            'rolled_over' => false,
            'dry_run' => false,
            'conditions' => ['[max_docs: 100]' => false],
        ];
        $response = $this->withResponse($status)->indexAPICall('movies/_rollover/movies-000002', 'POST', [
            'conditions' => ['max_docs' => 100],
        ]);

        $this->assertSame($status, $response->json());
    }

    public function test_document_write_requests_preserve_integral_floats(): void
    {
        $source = ['price' => 12.0, 'coordinates' => [1.0, 0.0]];
        $bulk = new BulkRequest('POST', new Uri('/movies/_bulk'), [
            ['create' => ['_id' => '1']],
            $source,
        ]);
        $update = new HttpRequest('POST', new Uri('/movies/_update/1'), ['doc' => $source]);

        $this->assertSame($source, $bulk->body()[1]);
        $this->assertSame($source, $update->body()['doc']);
    }

    public function test_replacing_a_json_request_body_updates_its_decoded_payload(): void
    {
        $request = new HttpRequest('POST', new Uri('/movies/_search'), ['size' => 1]);
        $updated = $request->withBody(Utils::streamFor('{"size":2}'));

        $this->assertSame(['size' => 2], $updated->body());
        $this->assertSame(['size' => 1], $request->body());
    }

    /** @dataProvider ndjsonRequestTypes */
    public function test_replacing_an_ndjson_request_body_updates_its_decoded_payload(string $class, array $original, array $replacement): void
    {
        $request = new $class('POST', new Uri('/movies'), $original);
        $body = implode(PHP_EOL, array_map(fn (array $line): string => json_encode($line, JSON_THROW_ON_ERROR), $replacement)).PHP_EOL;
        $updated = $request->withBody(Utils::streamFor($body));

        $this->assertSame([...$replacement, null], $updated->body());
        $this->assertSame([...$original, null], $request->body());
    }

    public static function ndjsonRequestTypes(): array
    {
        return [
            [BulkRequest::class, [['delete' => ['_id' => '1']]], [['delete' => ['_id' => '2']]]],
            [MSearchRequest::class, [['index' => 'movies'], ['size' => 1]], [['index' => 'movies'], ['size' => 2]]],
        ];
    }

    public function test_search_template_save_reports_missing_acknowledgement(): void
    {
        $template = $this->withResponse(['acknowledged' => false])->newTemplate('movies')->get();

        $this->expectException(ElasticsearchException::class);
        $template->save();
    }

    /**
     * @dataProvider searchRequests
     *
     * @withoutCluster
     */
    public function test_search_accepts_a_connection_response_implementing_the_contract(string $entryPoint): void
    {
        $response = $this->createStub(ElasticsearchResponse::class);
        $response->method('psr')->willReturn(new PsrResponse(200, [], '{"hits":{"hits":[{"_id":"movie"}]}}'));
        $connection = $this->createStub(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturn($response);
        $sigmie = new Sigmie($connection);

        $search = match ($entryPoint) {
            'get' => $sigmie->query('movies')->get(),
            'response' => $sigmie->query('movies')->response(),
            'template' => (new ExistingScript('saved', $connection))->run('movies'),
        };

        $this->assertSame('movie', $search->hits()[0]->_id);
    }

    public function searchRequests(): array
    {
        return [['get'], ['response'], ['template']];
    }

    public function test_async_node_selection_failure_rejects_the_promise(): void
    {
        $failure = new NoNodeAvailableException('No live nodes');
        $http = $this->createStub(JSONClient::class);
        $http->method('promise')->willThrowException($failure);
        $connection = new HttpConnection($http);

        $promise = $connection->promise(new HttpRequest('GET', new Uri('/movies')));
        $this->assertSame(Promise::REJECTED, $promise->getState());
        $this->assertSame($failure, $promise->then(null, fn ($reason) => $reason)->wait());
    }

    public function test_replacing_hits_on_a_response_copy_preserves_the_original(): void
    {
        $original = new Search(200, [], '{"hits":{"hits":[{"_id":"first","_source":{}}]}}');
        $this->assertSame('first', $original->hits()[0]->_id);
        $copy = $original->withHeader('X-Trace', 'copy');
        $copy->replaceHits([]);

        $this->assertCount(1, $original->hits());
        $this->assertSame([], $copy->hits());
    }

    public function test_replaced_response_body_supplies_new_documents(): void
    {
        $response = new Mget(200, [], '{"docs":[{"found":true,"_id":"old","_source":{}}]}');
        $this->assertSame('old', $response->first()->_id);
        $updated = $response->withBody(Utils::streamFor('{"docs":[]}'));

        $this->assertSame([], $updated->docs());
        $this->assertSame('old', $response->first()->_id);
    }

    public function test_exception_inspection_preserves_request_stream_position(): void
    {
        $request = new HttpRequest('POST', new Uri('/movies/_search'), ['size' => 2]);
        $request->getBody()->seek(2);
        $error = (new Response(400, [], '{"error":"request failed"}'))->exception($request);

        $this->assertSame(2, $error->json('request.size'));
        $this->assertSame(2, $request->getBody()->tell());
    }

    public function test_raw_exception_preserves_fractional_type(): void
    {
        $exception = new ElasticsearchException(['request' => ['boost' => 1.0]], 400);

        $this->assertSame($exception->json(), $exception->toRaw());
    }

    public function test_lowercase_index_write_checks_acknowledgement(): void
    {
        $this->expectException(ElasticsearchException::class);
        $this->withResponse(['acknowledged' => false])->indexAPICall('movies', 'put', []);
    }

    public function test_cluster_api_accepts_a_contract_response(): void
    {
        $response = $this->createStub(ElasticsearchResponse::class);
        $connection = $this->createStub(ElasticsearchConnection::class);
        $connection->method('__invoke')->willReturn($response);
        $client = new class
        {
            use Cluster { clusterAPICall as public; }
        };
        $client->setElasticsearchConnection($connection);

        $this->assertSame($response, $client->clusterAPICall('health'));
    }

    public function test_serializing_http_hits_preserves_numeric_search_metadata_keys(): void
    {
        $response = new Search(200, [], '{"hits":{"hits":[{"_id":"1","fields":{"0":[42]},"highlight":{"0":["<em>42</em>"]},"inner_hits":{"0":{"hits":{"hits":[{"_id":"1","fields":{"0":[42]}}]}}}}]}}');

        $json = json_decode(json_encode($response->hits()[0], JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

        $this->assertSame([42], $json->fields->{'0'} ?? null);
        $this->assertSame(['<em>42</em>'], $json->highlight->{'0'} ?? null);
        $this->assertSame([42], $json->inner_hits->{'0'}->hits->hits[0]->fields->{'0'} ?? null);
    }

    public function test_inner_hit_sources_serialize_as_json_objects(): void
    {
        $response = new Search(200, [], '{"hits":{"hits":[{"inner_hits":{"top":{"hits":{"hits":[{"_source":{}},{"_source":{"0":"value"}}]}}}}]}}');
        $json = json_decode(json_encode($response->hits()[0], JSON_THROW_ON_ERROR));
        $inner = $json->inner_hits->top->hits->hits;

        $this->assertIsObject($inner[0]->_source);
        $this->assertSame('value', $inner[1]->_source->{'0'} ?? null);
    }

    public function test_async_query_rejects_when_transport_cannot_start_the_request(): void
    {
        $failure = new NoNodeAvailableException('No live nodes');
        $pool = $this->createStub(NodePoolInterface::class);
        $pool->method('setHosts')->willReturnSelf();
        $pool->method('nextNode')->willThrowException($failure);
        $transport = TransportBuilder::create()->setNodePool($pool)->build()
            ->setAsyncClient($this->createStub(HttpAsyncClient::class));
        $sigmie = new Sigmie(new HttpConnection(new TransportClient($transport)));

        $promise = $sigmie->query('movies')->promise();

        $this->assertSame(Promise::REJECTED, $promise->getState());
        $this->expectExceptionObject($failure);
        $promise->wait();
    }

    public function test_multisearch_accepts_a_connection_response_implementing_the_contract(): void
    {
        $hits = ['hits' => ['hits' => []]];
        $response = $this->createStub(ElasticsearchResponse::class);
        $response->method('code')->willReturn(200);
        $response->method('json')->with('responses')->willReturn([$hits]);
        $connection = $this->createStub(ElasticsearchConnection::class);
        $connection->method('__invoke')->willReturn($response);

        $multi = (new Sigmie($connection))->newMultiSearch();
        $multi->raw('movies', []);
        $this->assertSame([$hits], $multi->get());
    }

    public function test_unsuccessful_opensearch_pit_close_is_reported(): void
    {
        $connection = $this->createStub(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Opensearch);
        $connection->method('__invoke')->willReturn(new Response(200, [], '{"pits":[{"pit_id":"snapshot","successful":false}]}'));

        $this->expectException(ElasticsearchException::class);
        (new PointInTimeRequests($connection))->close('snapshot');
    }

    public function test_one_bulk_delete_retains_the_ndjson_operation_list(): void
    {
        $operation = ['delete' => ['_id' => '1']];
        $request = new BulkRequest('POST', new Uri('/movies/_bulk'), [$operation]);
        $response = new Response(403, [], '{"error":"blocked"}');

        $this->assertSame([$operation], $response->exception($request)->json('request'));
    }

    public function test_exception_recognizes_mixed_case_ndjson_media_types(): void
    {
        $operation = ['delete' => ['_id' => '1']];
        $request = (new BulkRequest('POST', new Uri('/movies/_bulk'), [$operation]))
            ->withHeader('Content-Type', 'Application/X-NDJSON');
        $response = new Response(403, [], '{"error":"blocked"}');

        $this->assertSame([$operation], $response->exception($request)->json('request'));
    }

    public function test_exception_paths_reach_dotted_field_names(): void
    {
        $response = new Response(400, [], '{"error":"invalid mapping"}');
        $request = new HttpRequest('PUT', new Uri('/movies/_mapping'), [
            'properties' => ['actor.name' => ['type' => 'keyword']],
        ]);

        $this->assertSame('keyword', $response->exception($request)->json('request.properties.actor.name.type'));
    }

    public function test_partial_search_exception_identifies_the_shard_failure(): void
    {
        $response = new Search(200, [], '{"_shards":{"failed":1,"failures":[{"reason":{"type":"query_shard_exception"}}]}}');
        $request = new HttpRequest('POST', new Uri('/movies/_search'));

        $this->assertSame('query_shard_exception', $response->exception($request)->json('type'));
    }

    public function test_pipeline_creation_reports_missing_acknowledgement(): void
    {
        $connection = $this->withResponse(['acknowledged' => false])->getElasticsearchConnection();

        $this->expectException(ElasticsearchException::class);
        (new NewPipeline($connection, 'reports'))->create();
    }

    public function test_exception_diagnostics_keep_unsigned_request_numbers(): void
    {
        $query = '{"query":{"term":{"serial":18446744073709551615}}}';
        $response = Response::fromPsrResponse(new PsrResponse(503, [], '{"error":"unavailable"}'));
        foreach (['/_search' => $query, '/_msearch' => "{}\n".$query."\n"] as $path => $body) {
            $request = (new HttpRequest('POST', new Uri($path)))->withBody(Utils::streamFor($body));
            $payload = $response->exception($request)->json('request');
            $search = $path === '/_search' ? $payload : $payload[1];

            $this->assertSame('18446744073709551615', $search['query']['term']['serial']);
        }
    }

    /** @dataProvider partialSearchResponses */
    public function test_direct_search_rejects_partial_hits(array $payload): void
    {
        $sigmie = $this->withResponse($payload);

        $this->expectException(ElasticsearchException::class);
        $sigmie->rawQuery('movies', ['query' => ['match_all' => (object) []]]);
    }

    /** @dataProvider partialSearchResponses */
    public function test_multi_search_rejects_partial_hits(array $payload): void
    {
        $multi = $this->withResponse(['responses' => [$payload]])->newMultiSearch();
        $multi->raw('movies', ['query' => ['match_all' => (object) []]]);

        $this->expectException(ElasticsearchException::class);
        $multi->hits();
    }

    public static function partialSearchResponses(): array
    {
        $hits = ['hits' => ['total' => 0, 'hits' => []]];

        return [
            'timeout' => [[...$hits, 'timed_out' => true]],
            'partial remote cluster' => [[...$hits, '_clusters' => ['total' => 2, 'partial' => 1, 'successful' => 1]]],
            'failed remote cluster' => [[...$hits, '_clusters' => ['total' => 2, 'failed' => 1, 'successful' => 1]]],
            'failed shard' => [[...$hits, '_shards' => ['total' => 2, 'successful' => 1, 'failed' => 1]]],
            'unavailable shard' => [[...$hits, '_shards' => ['total' => 2, 'successful' => 1, 'failed' => 0]]],
        ];
    }

    public function test_async_direct_search_rejects_a_timed_out_response(): void
    {
        $http = $this->createMock(JSONClient::class);
        $http->method('promise')->willReturn(new FulfilledPromise(
            JSONResponse::fromPsrResponse(new PsrResponse(200, [], '{"timed_out":true,"hits":{"hits":[]}}')),
        ));
        $sigmie = new Sigmie(new HttpConnection($http));

        $this->expectException(ElasticsearchException::class);
        $sigmie->query('movies')->promise()->wait();
    }

    public function test_an_index_named_error_can_be_read(): void
    {
        $sigmie = $this->withResponse(['error' => [
            'settings' => ['index' => ['number_of_shards' => '1', 'number_of_replicas' => '0']],
            'mappings' => ['properties' => []],
        ]]);

        $this->assertSame('error', $sigmie->index('error')->name);
    }

    public function test_an_unfollowed_redirect_is_a_failed_request(): void
    {
        $sigmie = $this->withResponse([], 307);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(307);
        $sigmie->indexAPICall('movies', 'HEAD');
    }

    public function test_force_merge_reports_failed_shards(): void
    {
        $sigmie = $this->withResponse(['_shards' => [
            'total' => 2, 'successful' => 1, 'failed' => 1,
            'failures' => [['reason' => ['type' => 'already_closed_exception', 'reason' => 'engine is closed']]],
        ]]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('engine is closed');
        $sigmie->indexAPICall('movies/_forcemerge', 'POST');
    }

    public function test_a_single_document_delete_reports_failed_shards(): void
    {
        $sigmie = $this->withResponse(['result' => 'deleted', '_shards' => [
            'total' => 2, 'successful' => 1, 'failed' => 1,
            'failures' => [['reason' => ['type' => 'already_closed_exception', 'reason' => 'engine is closed']]],
        ]]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('engine is closed');
        $sigmie->collect('movies')->remove('1');
    }

    public function test_bulk_alias_action_exposes_the_action_error(): void
    {
        $sigmie = $this->withResponse(['acknowledged' => true, 'errors' => true, 'action_results' => [
            ['status' => 404, 'error' => ['type' => 'aliases_not_found_exception', 'reason' => 'aliases [missing] missing']],
            ['status' => 200],
        ]]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('aliases [missing] missing');
        $sigmie->aliasAPICall('POST', ['actions' => [
            ['remove' => ['index' => 'old', 'alias' => 'missing']],
            ['add' => ['index' => 'movies', 'alias' => 'live']],
        ]]);
    }

    public function test_create_index_rejects_unacknowledged_creation(): void
    {
        $sigmie = Sigmie::create('http://localhost:9200', config: [
            'handler' => new MockHandler([
                new PsrResponse(404),
                new PsrResponse(200, [], '{"acknowledged":false,"shards_acknowledged":false,"index":"movies"}'),
                new PsrResponse(200, [], '{"acknowledged":true}'),
            ]),
        ]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('acknowledged');
        $sigmie->newIndex('movies')->create();
    }

    public function test_clear_reports_delete_by_query_failures(): void
    {
        $sigmie = Sigmie::create('http://localhost:9200', config: [
            'handler' => new MockHandler([
                new PsrResponse(200, [], '{"_shards":{"failed":0}}'),
                new PsrResponse(200, [], json_encode(['deleted' => 1, 'failures' => [
                    ['status' => 403, 'cause' => ['type' => 'cluster_block_exception', 'reason' => 'index write blocked']],
                ]], JSON_THROW_ON_ERROR)),
            ]),
        ]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('index write blocked');
        $sigmie->collect('movies')->clear();
    }

    public function test_update_by_query_reports_conflicts_without_item_errors(): void
    {
        $sigmie = $this->withResponse(['timed_out' => false, 'version_conflicts' => 2, 'failures' => []]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('version_conflicts');
        $sigmie->indexAPICall('movies/_update_by_query?conflicts=proceed', 'POST');
    }

    public function test_sync_reindex_reports_a_timed_out_copy(): void
    {
        $sigmie = $this->withResponse(['timed_out' => true, 'created' => 1, 'failures' => []]);
        $index = new AliasedIndex('movies', 'live');
        $index->setElasticsearchConnection($sigmie->getElasticsearchConnection());

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('timed_out');
        $index->reindexAPICall('movies', 'copy');
    }

    /** @dataProvider partialTaskListFailures */
    public function test_task_list_surfaces_partial_failures(string $key): void
    {
        $sigmie = $this->withResponse([
            'nodes' => [],
            $key => [['type' => 'failed_node_exception', 'reason' => 'node left the cluster']],
        ]);

        $client = new class
        {
            use Tasks;
        };
        $client->setElasticsearchConnection($sigmie->getElasticsearchConnection());

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('node left the cluster');
        $client->tasksAPICall();
    }

    public static function partialTaskListFailures(): array
    {
        return [['node_failures'], ['task_failures']];
    }

    public function test_completed_update_by_query_task_surfaces_its_partial_failure(): void
    {
        $sigmie = $this->withResponse([
            'completed' => true,
            'task' => ['action' => 'indices:data/write/update/byquery', 'cancelled' => false],
            'response' => ['timed_out' => false, 'failures' => [
                ['status' => 429, 'cause' => ['type' => 'es_rejected_execution_exception', 'reason' => 'write queue is full']],
            ]],
        ]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(429);
        $this->expectExceptionMessage('write queue is full');
        $sigmie->indexAPICall('_tasks/node:42', 'GET');
    }

    public function test_completed_reindex_task_surfaces_cancellation(): void
    {
        $sigmie = $this->withResponse([
            'completed' => true,
            'task' => ['action' => 'indices:data/write/reindex', 'cancelled' => true],
            'response' => ['timed_out' => false, 'failures' => [], 'canceled' => 'by user request'],
        ]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('by user request');
        $sigmie->indexAPICall('_tasks/node:42', 'GET');
    }

    public function test_multi_search_raw_hits_surface_an_item_error(): void
    {
        $multi = $this->withResponse(['responses' => [
            ['error' => ['type' => 'index_not_found_exception', 'reason' => 'index [missing] is gone'], 'status' => 404],
        ]])->newMultiSearch();
        $multi->raw('missing', ['query' => ['match_all' => (object) []]]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('index [missing] is gone');
        $multi->hits();
    }

    /** @dataProvider unacknowledgedIndexDeleteMethods */
    public function test_delete_reports_unacknowledged_deletion(string $method): void
    {
        $sigmie = Sigmie::create('http://localhost:9200', config: [
            'handler' => new MockHandler([
                new PsrResponse(200, [], '[{"index":"movies","health":"green","status":"open","uuid":"a","pri":"1","rep":"0","docs.count":"0","docs.deleted":"0","store.size":"225b","pri.store.size":"225b","creation.date.string":"2026-11-24T00:00:00.000Z"}]'),
                new PsrResponse(200, [], '[]'),
                new PsrResponse(200, [], '{"acknowledged":false}'),
            ]),
        ]);

        $this->assertFalse($sigmie->{$method}('movies'));
    }

    public static function unacknowledgedIndexDeleteMethods(): array
    {
        return [['delete'], ['deleteIfExists']];
    }

    public function test_deleting_an_alias_and_its_physical_index_deletes_once(): void
    {
        $deleted = [];
        $sigmie = Sigmie::create('http://localhost:9200', config: [
            'handler' => function (Request $request) use (&$deleted): PromiseInterface {
                $path = $request->getUri()->getPath();
                if ($request->getMethod() === 'DELETE') {
                    $deleted[] = $path;

                    return Create::promiseFor(new PsrResponse(200, [], '{"acknowledged":true}'));
                }

                $response = match (true) {
                    $path === '/_cat/aliases' => new PsrResponse(200, [], '[{"index":"movies_2026","alias":"movies"}]'),
                    $deleted !== [] => new PsrResponse(404, [], '{"error":{"type":"index_not_found_exception"}}'),
                    default => new PsrResponse(200, [], '[{"index":"movies_2026","health":"green","status":"open","uuid":"a","pri":"1","rep":"0","docs.count":"0","docs.deleted":"0","store.size":"225b","pri.store.size":"225b","creation.date.string":"2026-11-24T00:00:00.000Z"}]'),
                };

                return Create::promiseFor($response);
            },
        ]);

        $this->assertTrue($sigmie->delete('movies,movies_2026'));
        $this->assertSame(['/movies_2026'], $deleted);
    }

    public function test_document_assertions_check_dot_ids_without_putting_them_in_the_path(): void
    {
        $sigmie = Sigmie::create('http://localhost:9200', config: [
            'handler' => static function (Request $request): PromiseInterface {
                if ($request->getUri()->getPath() !== '/articles/_mget') {
                    return Create::promiseFor(new PsrResponse(404));
                }

                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $id = $body['docs'][0]['_id'];
                $response = ['docs' => [['_index' => 'articles', '_id' => $id, 'found' => $id === '.']]];

                return Create::promiseFor(new PsrResponse(200, [], json_encode($response, JSON_THROW_ON_ERROR)));
            },
        ]);
        $index = new IndexAssert('articles', []);
        $index->setElasticsearchConnection($sigmie->getElasticsearchConnection());

        $index->assertDocumentExists('articles', new Document(_id: '.'));
        $index->assertDocumentIsMissing('articles', new Document(_id: '..'));
    }

    public function test_autocompletion_reads_options_for_every_token(): void
    {
        $response = $this->withResponse(['suggest' => ['autocompletion' => [
            ['text' => 'corect', 'options' => [['text' => 'correct']]],
            ['text' => 'speling', 'options' => [['text' => 'spelling']]],
        ]]])->rawQuery('movies', ['suggest' => ['autocompletion' => [
            'text' => 'corect speling', 'term' => ['field' => 'title'],
        ]]]);

        $this->assertSame(['correct', 'spelling'], $response->autocompletion());
    }

    private function withResponse(array $payload, int $status = 200): Sigmie
    {
        return Sigmie::create('http://localhost:9200', config: [
            'handler' => new MockHandler([new PsrResponse($status, [], json_encode($payload, JSON_THROW_ON_ERROR))]),
        ]);
    }

    public function test_audit_count_and_stats_without_targets_use_cluster_endpoints(): void
    {
        $connection = $this->capturingConnection();
        $client = new class
        {
            use Count { countAPICall as public; }
            use Stats { statsAPICall as public; }
        };
        $client->setElasticsearchConnection($connection);

        $client->countAPICall('');
        $this->assertSame('/_count', $connection->path);

        $client->statsAPICall('');
        $this->assertSame('/_stats', $connection->path);
    }

    public function test_audit_date_math_slash_stays_inside_the_index_path_segment(): void
    {
        $connection = $this->capturingConnection();
        $sigmie = new Sigmie($connection);

        $sigmie->indexAPICall('<movies-{now/d}>/_settings?flat_settings=true', 'GET');
        $this->assertSame('/%3Cmovies-%7Bnow%2Fd%7D%3E/_settings', $connection->path);
        $this->assertSame('flat_settings=true', $connection->query);

        $client = new class
        {
            use Cat { catAPICall as public; }
        };
        $client->setElasticsearchConnection($connection);
        $client->catAPICall('indices/<movies-{now/d}>', 'GET');

        $this->assertSame('/_cat/indices/%3Cmovies-%7Bnow%2Fd%7D%3E', $connection->path);
    }

    public function test_replacing_hits_with_filtered_results_keeps_a_json_list(): void
    {
        $response = new Search(200, [], '{"hits":{"hits":[{"_id":"a","_score":1},{"_id":"b","_score":1}]}}');
        $filtered = array_filter($response->json('hits.hits'), fn (array $hit): bool => $hit['_id'] === 'b');
        $hits = $response->replaceHits($filtered);

        $this->assertSame([0], array_keys($hits));
        $this->assertSame('b', $response->json('hits.hits.0._id'));
    }

    public function test_unsigned_long_sort_values_preserve_every_digit(): void
    {
        $response = new Search(200, [], '{"hits":{"hits":[{"_id":"1","_score":null,"sort":[18446744073709551615],"_source":{"u":18446744073709551615}}]}}');
        $hit = $response->hits()[0];

        $this->assertSame(['18446744073709551615'], $hit->sort);
        $this->assertSame('18446744073709551615', $hit['u']);
    }

    public function test_template_api_sends_empty_parameters_as_an_object(): void
    {
        $handler = new MockHandler([new PsrResponse(200, [], '{}')]);
        $client = Sigmie::create('http://127.0.0.1:9200', config: ['handler' => $handler]);
        $runner = new class
        {
            use SearchTemplate { templateAPICall as public run; }
        };
        $runner->setElasticsearchConnection($client->getElasticsearchConnection());

        $runner->run('movies', 'popular', []);
        $this->assertSame('{"id":"popular","params":{}}', (string) $handler->getLastRequest()->getBody());
    }

    public function test_shared_handler_keeps_network_errors_on_their_own_client(): void
    {
        $firstHost = 'http://127.0.0.1:9100';
        $secondHost = 'http://127.0.0.1:9101';
        $stack = HandlerStack::create(new MockHandler([
            new ConnectException('First node failed', new Request('GET', $firstHost)),
            new ConnectException('Second node failed', new Request('GET', $secondHost)),
        ]));
        $first = Sigmie::create($firstHost, config: ['handler' => $stack]);
        $second = Sigmie::create($secondHost, config: ['handler' => $stack]);
        $this->assertFalse($first->isConnected());
        $this->assertFalse($second->isConnected());

        try {
            $first->indexAPICall('movies', 'GET');
            $this->fail('The node pool is empty.');
        } catch (NoNodeAvailableException $noNodeAvailableException) {
            $this->assertNull($noNodeAvailableException->getPrevious());
        }
    }

    public function test_new_query_accepts_the_cluster_wide_target(): void
    {
        $handler = new MockHandler([new PsrResponse(200, [], '{"hits":{"total":2}}')]);
        $client = Sigmie::create('http://127.0.0.1:9200', config: ['handler' => $handler]);

        $this->assertSame(2, $client->newQuery('')->matchAll()->get()->total());
        $this->assertSame('/_search', $handler->getLastRequest()->getRequestTarget());
    }

    public function test_mget_sends_list_parameters_as_comma_separated_values(): void
    {
        $handler = new MockHandler([new PsrResponse(200, [], '{"docs":[]}')]);
        $client = Sigmie::create('http://127.0.0.1:9200', config: ['handler' => $handler]);

        $client->collect('movies')->mgetAPICall('movies', ['ids' => ['1']], [
            '_source_includes' => ['title', 'year'],
            'realtime' => false,
        ]);
        parse_str($handler->getLastRequest()->getUri()->getQuery(), $query);

        $this->assertSame(['_source_includes' => 'title,year', 'realtime' => 'false'], $query);
    }

    public function test_pipeline_id_with_a_slash_stays_in_one_path_segment(): void
    {
        $handler = new MockHandler([new PsrResponse(200, [], '{}'), new PsrResponse(200, [], '{}')]);
        $client = Sigmie::create('http://127.0.0.1:9200', config: ['handler' => $handler]);

        $pipeline = (new NewPipeline($client->getElasticsearchConnection(), 'reports/normalize'))->create();
        $path = '/_ingest/pipeline/reports%2Fnormalize';
        $this->assertSame($path, $handler->getLastRequest()->getUri()->getPath());
        $pipeline->simulate();
        $this->assertSame($path.'/_simulate', $handler->getLastRequest()->getUri()->getPath());
    }

    public function test_host_scheme_is_case_insensitive(): void
    {
        $handler = new MockHandler([new PsrResponse(200)]);
        $client = Sigmie::create(['HTTPS://127.0.0.1:9243/Elastic'], config: ['handler' => $handler]);
        $client->indexAPICall('movies', 'GET');

        $this->assertSame('https://127.0.0.1:9243/Elastic/movies', (string) $handler->getLastRequest()->getUri());
    }

    public function test_empty_search_target_uses_the_cluster_endpoint(): void
    {
        $handler = new MockHandler([new PsrResponse(200, [], '{}'), new PsrResponse(200, [], '{}')]);
        $client = Sigmie::create('http://127.0.0.1:9200', config: ['handler' => $handler]);

        $client->rawQuery('', []);
        $this->assertSame('http://127.0.0.1:9200/_search', (string) $handler->getLastRequest()->getUri());
        $client->template('daily')->run('');
        $this->assertSame('http://127.0.0.1:9200/_search/template', (string) $handler->getLastRequest()->getUri());
    }

    public function test_spaces_between_hosts_do_not_change_the_nodes(): void
    {
        $handler = new MockHandler([new PsrResponse(200), new PsrResponse(200)]);
        $client = Sigmie::create('http://127.0.0.1:9100, http://127.0.0.1:9101', config: ['handler' => $handler]);

        $ports = [];
        for ($i = 0; $i < 2; $i++) {
            $client->indexAPICall('movies', 'GET');
            $ports[] = $handler->getLastRequest()->getUri()->getPort();
        }

        sort($ports);
        $this->assertSame([9100, 9101], $ports);
    }

    public function test_plugin_names_match_exactly(): void
    {
        $previous = Sigmie::$plugins;
        try {
            Sigmie::registerPlugins('1');
            $this->assertFalse(Sigmie::isPluginRegistered('01'));
        } finally {
            Sigmie::$plugins = $previous;
        }
    }

    public function test_registering_keyed_plugin_lists_retains_previous_names(): void
    {
        $previous = Sigmie::$plugins;
        try {
            Sigmie::registerPlugins(['analysis' => 'analysis-icu']);
            Sigmie::registerPlugins(['analysis' => 'analysis-phonetic']);
            $this->assertTrue(Sigmie::isPluginRegistered('analysis-icu'));
            $this->assertTrue(Sigmie::isPluginRegistered('analysis-phonetic'));
        } finally {
            Sigmie::$plugins = $previous;
        }
    }

    public function test_async_query_uses_the_configured_client(): void
    {
        $handler = new MockHandler([new PsrResponse(200, [], '{"hits":{"total":3}}')]);
        $client = Sigmie::create('http://127.0.0.1:1', config: [
            'headers' => ['X-Request-Group' => 'reports'],
            'handler' => $handler,
        ]);

        $result = $client->query('movies')->promise()->wait();

        $this->assertSame('reports', $handler->getLastRequest()->getHeaderLine('X-Request-Group'));
        $this->assertSame(3, $result->total());
    }

    public function test_audit_index_non_json_error_keeps_a_status_description(): void
    {
        $sigmie = Sigmie::create('http://localhost:9200', config: [
            'handler' => new MockHandler([new PsrResponse(502, [], 'Bad gateway')]),
        ]);

        try {
            $sigmie->indexAPICall('movies', 'GET');
            $this->fail('The request failed.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame('Request failed with code 502.', $elasticsearchException->json('type'));
            $this->assertSame('Bad gateway', $elasticsearchException->json('body'));
        }
    }

    public function test_audit_index_async_search_rejects_an_elasticsearch_error(): void
    {
        $http = $this->createMock(JSONClient::class);
        $http->method('promise')->willReturn(new FulfilledPromise(
            JSONResponse::fromPsrResponse(new PsrResponse(404, [], '{"error":{"type":"index_not_found_exception"}}')),
        ));

        $sigmie = new Sigmie(new HttpConnection($http));

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(404);
        $sigmie->newQuery('missing')->matchAll()->promise()->wait();
    }

    public function test_audit_index_head_distinguishes_a_cluster_error_from_a_missing_index(): void
    {
        $sigmie = Sigmie::create('http://localhost:9200', config: [
            'handler' => new MockHandler([
                new PsrResponse(404),
                new PsrResponse(503),
            ]),
        ]);

        $this->assertSame(404, $sigmie->indexAPICall('missing', 'HEAD')->code());

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(503);
        $sigmie->indexAPICall('unavailable', 'HEAD');
    }

    /**
     * @test
     */
    public function update_posts_the_given_document_body(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            public string $body = '';

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $this->body = (string) $request->getBody();

                return new Response(200, [], '{}');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }

            public function driver(): SearchEngine
            {
                throw new RuntimeException('no cluster');
            }

            public function isServerless(): bool
            {
                return false;
            }
        };

        $client = new class
        {
            use Update;

            public function send(array $data): void
            {
                $this->updateAPICall('movies', '1', $data);
            }
        };
        $client->setElasticsearchConnection($connection);

        $client->send(['doc' => ['title' => 'Updated']]);

        $this->assertSame(
            ['doc' => ['title' => 'Updated']],
            json_decode($connection->body, true),
        );
    }

    /**
     * @test
     */
    public function a_hit_or_mget_doc_without_source_is_an_empty_document(): void
    {
        $hits = (new Search(200, [], json_encode([
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_index' => 'movies', '_score' => 1.0],
                    ['_id' => '2', '_index' => 'movies', '_score' => 1.0, '_source' => null],
                ],
            ],
        ])))->hits();

        $this->assertCount(2, $hits);
        $this->assertInstanceOf(Hit::class, $hits[0]);
        $this->assertSame([], $hits[0]->_source);
        $this->assertSame([], $hits[1]->_source);

        $docs = (new Mget(200, [], json_encode([
            'docs' => [
                ['found' => true, '_id' => '1', '_index' => 'movies'],
                ['found' => true, '_id' => '2', '_index' => 'movies', '_source' => null],
                ['found' => false, '_id' => '3', '_index' => 'movies'],
            ],
        ])))->docs();

        $this->assertCount(2, $docs);
        $this->assertInstanceOf(Document::class, $docs[0]);
        $this->assertSame([], $docs[0]->_source);
        $this->assertSame([], $docs[1]->_source);
        $this->assertSame('movies', $docs[0]->_index);
    }

    /**
     * @test
     */
    public function an_exception_keeps_the_request_body(): void
    {
        $request = new HttpRequest('POST', new Uri('/movies/_search'), [
            'query' => ['match_all' => (object) []],
        ]);
        // Sending reads the body stream to its end.
        $request->getBody()->getContents();

        $response = Response::fromPsrResponse(new PsrResponse(400, ['Content-Type' => 'application/json'], json_encode([
            'error' => ['type' => 'search_phase_execution_exception'],
        ])));

        $payload = json_decode($response->exception($request)->getMessage(), true);

        $this->assertSame(['match_all' => []], $payload['request']['query']);
    }

    /**
     * @test
     */
    public function an_ndjson_exception_keeps_the_operations(): void
    {
        $bulk = new BulkRequest('POST', new Uri('/movies/_bulk'), [
            ['index' => ['_id' => '1']],
            ['title' => 'Matrix'],
        ]);
        $msearch = new MSearchRequest('POST', new Uri('/_msearch'), [
            ['index' => 'movies'],
            ['query' => ['match_all' => (object) []]],
        ]);
        $response = \Sigmie\Base\Http\Responses\Bulk::fromPsrResponse(new PsrResponse(400, [], json_encode([
            'error' => ['type' => 'illegal_argument_exception', 'reason' => 'malformed'],
        ])));

        $bulkPayload = json_decode($response->exception($bulk)->getMessage(), true);
        $msearchPayload = json_decode($response->exception($msearch)->getMessage(), true);

        $this->assertSame([
            ['index' => ['_id' => '1']],
            ['title' => 'Matrix'],
        ], $bulkPayload['request']);
        $this->assertSame([
            ['index' => 'movies'],
            ['query' => ['match_all' => []]],
        ], $msearchPayload['request']);
    }

    /**
     * @test
     */
    public function replace_hits_reads_the_body_before_writing(): void
    {
        $search = new Search(200, [], json_encode([
            'hits' => [
                'total' => ['value' => 1, 'relation' => 'eq'],
                'hits' => [[
                    '_id' => '1',
                    '_index' => 'movies',
                    '_score' => 1.0,
                    '_source' => ['title' => 'Old'],
                ]],
            ],
        ]));

        $hits = $search->replaceHits([[
            '_id' => '1',
            '_index' => 'movies',
            '_score' => 2.0,
            '_source' => ['title' => 'New'],
        ]]);

        $this->assertCount(1, $hits);
        $this->assertSame('New', $hits[0]->_source['title']);
    }

    /**
     * @test
     */
    public function a_document_id_with_a_hash_stays_in_the_path(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            public array $paths = [];

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $path = $request->getUri()->getPath();
                $this->paths[] = $path;
                $code = $request->getMethod() === 'HEAD' ? 404 : 200;

                if (str_ends_with($path, '/_mget')) {
                    return new Mget($code, [], '{}');
                }

                if (str_ends_with($path, '/_bulk')) {
                    return new \Sigmie\Base\Http\Responses\Bulk($code, [], '{}');
                }

                if (str_contains($path, '/_search')) {
                    return new Search($code, [], '{}');
                }

                return new Response($code, [], '{}');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };

        $client = new class
        {
            use Analyze;
            use Bulk;
            use Count;
            use Delete;
            use Doc;
            use Explain;
            use IndexApi;
            use Ingest;
            use MgetApi;
            use Script;
            use SearchApi;
            use SearchTemplate;
            use Stats;
            use Update;

            public function send(string $id): void
            {
                $this->docAPICall('movies', $id, 'HEAD');
                $this->deleteAPICall('movies', $id);
                $this->updateAPICall('movies', $id, ['doc' => []]);
                $this->explainAPICall('movies', ['match_all' => (object) []], $id);
                $this->mgetAPICall($id);
                $this->bulkAPICall($id, []);
                $this->countAPICall($id);
                $this->analyzeAPICall($id, 'text', 'default');
                $this->statsAPICall($id);
                $this->searchAPICall($id, []);
                $this->templateAPICall($id, 'saved', []);
                $this->openPointInTimeAPICall($id);
                $this->indexAPICall($id.'/_refresh', 'POST');
                $this->indexAPICall($id.'/_delete_by_query?refresh=true', 'POST');
                $this->indexAPICall($id.'_*', 'GET');
                $this->ingestAPICall($id, 'GET');
                $this->scriptAPICall('GET', $id);
            }
        };
        $client->setElasticsearchConnection($connection);
        $client->send('foo#bar');

        // Template and SearchTemplate both define templateAPICall, so each needs its own client.
        $templates = new class
        {
            use Template;

            public function send(string $id): void
            {
                $this->templateAPICall($id, 'GET');
            }
        };
        $templates->setElasticsearchConnection($connection);
        $templates->send('foo#bar');

        $this->assertSame([
            '/movies/_doc/foo%23bar',
            '/movies/_doc/foo%23bar',
            '/movies/_update/foo%23bar',
            '/movies/_explain/foo%23bar',
            '/foo%23bar/_mget',
            '/foo%23bar/_bulk',
            '/foo%23bar/_count',
            '/foo%23bar/_analyze',
            '/foo%23bar/_stats',
            '/foo%23bar/_search',
            '/foo%23bar/_search/template',
            '/foo%23bar/_pit',
            '/foo%23bar/_refresh',
            '/foo%23bar/_delete_by_query',
            '/foo%23bar_*',
            '/_ingest/pipeline/foo%23bar',
            '/_scripts/foo%23bar',
            '/_template/foo%23bar',
        ], $connection->paths);
    }

    /**
     * @test
     */
    public function hits_keep_sort_and_nested_matches(): void
    {
        $hits = (new Search(200, [], json_encode([
            'hits' => [
                'hits' => [[
                    '_id' => '1',
                    '_index' => 'movies',
                    '_score' => 1.0,
                    '_source' => ['title' => 'A'],
                    'sort' => [3, 'matrix'],
                    '_matches' => [
                        'reviews' => [
                            'total' => 1,
                            'items' => [['_offset' => 0, 'comment' => 'Battery']],
                        ],
                    ],
                ]],
            ],
        ])))->hits();

        $this->assertSame([3, 'matrix'], $hits[0]->sort);
        $this->assertSame([['_offset' => 0, 'comment' => 'Battery']], $hits[0]->matches('reviews'));
    }

    /**
     * @test
     */
    public function hit_from_raw_accepts_a_null_source(): void
    {
        $hit = Hit::fromRaw([
            '_id' => '1',
            '_score' => 1.0,
            '_source' => null,
        ]);

        $this->assertSame([], $hit->_source);
        $this->assertSame('1', $hit->_id);
        $this->assertSame(1.0, $hit->_score);
    }

    /**
     * @test
     */
    public function a_hash_after_a_wildcard_stays_in_the_path(): void
    {
        $connection = $this->capturingConnection();
        $client = new class
        {
            use IndexApi;

            public function send(string $name): void
            {
                $this->indexAPICall($name, 'GET');
            }
        };
        $client->setElasticsearchConnection($connection);
        $client->send('foo_*#bar');

        $this->assertSame('/foo_*%23bar', $connection->path);
        $this->assertSame('', $connection->fragment);
    }

    /**
     * @test
     */
    public function a_comma_list_keeps_the_wildcard(): void
    {
        $connection = $this->capturingConnection();
        $client = new class
        {
            use IndexApi;

            public function send(string $name): void
            {
                $this->indexAPICall($name, 'GET');
            }
        };
        $client->setElasticsearchConnection($connection);
        $client->send('movies,logs*');

        $this->assertSame('/movies,logs*', $connection->path);
        $this->assertSame('', $connection->fragment);
    }

    /**
     * @test
     */
    public function a_cat_pattern_keeps_the_hash_and_the_columns(): void
    {
        $connection = $this->capturingConnection();
        $client = new class
        {
            use Cat;

            public function send(string $pattern): void
            {
                $columns = 'h=index,health,status,uuid,pri,rep,docs.count,docs.deleted,store.size,pri.store.size,creation.date.string';
                $this->catAPICall(sprintf('indices/%s?%s', $pattern, $columns), 'GET');
            }
        };
        $client->setElasticsearchConnection($connection);
        $client->send('foo#bar');

        $this->assertSame('/_cat/indices/foo%23bar', $connection->path);
        $this->assertSame('', $connection->fragment);
        $this->assertStringContainsString(
            'h=index,health,status,uuid,pri,rep,docs.count,docs.deleted,store.size,pri.store.size,creation.date.string',
            $connection->query,
        );
        $this->assertStringContainsString('format=json', $connection->query);

        $client->send('*');

        $this->assertSame('/_cat/indices/*', $connection->path);
        $this->assertStringContainsString('format=json', $connection->query);
    }

    /**
     * @test
     */
    public function a_search_index_list_keeps_the_comma(): void
    {
        $connection = new class extends HttpConnection
        {
            public array $paths = [];

            public array $queries = [];

            public SearchEngine $engine;

            public function __construct()
            {
                $this->engine = new Elasticsearch;
            }

            public function driver(): SearchEngine
            {
                return $this->engine;
            }

            public function isServerless(): bool
            {
                return false;
            }

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $uri = $request->getUri();
                $this->paths[] = $uri->getPath();
                $this->queries[] = $uri->getQuery();

                return new Search(200, [], '{"id":"pit-1","pit_id":"pit-1","hits":{"hits":[]}}');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                $uri = $request->getUri();
                $this->paths[] = $uri->getPath();
                $this->queries[] = $uri->getQuery();

                return new class implements Promise
                {
                    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): Promise
                    {
                        return $this;
                    }

                    public function getState(): string
                    {
                        return Promise::FULFILLED;
                    }

                    public function wait($unwrap = true): mixed
                    {
                        return null;
                    }
                };
            }
        };

        (new NewSearch($connection))->index('movies,logs')->promise();
        $this->assertSame('/movies,logs/_search', $connection->paths[0]);

        $connection->paths = [];
        (new NewSearch($connection))->index('movies,logs*')->promise();
        $this->assertSame('/movies,logs*/_search', $connection->paths[0]);

        $connection->paths = [];
        (new NewSearch($connection))->index('foo#bar')->promise();
        $this->assertSame('/foo%23bar/_search', $connection->paths[0]);

        $connection->paths = [];
        $connection->queries = [];
        iterator_to_array((new NewSearch($connection))->index('movies,logs')->lazy());
        $this->assertSame('/movies,logs/_pit', $connection->paths[0]);
        $this->assertSame('keep_alive=1m', $connection->queries[0]);

        $connection->engine = new Opensearch;
        $connection->paths = [];
        $connection->queries = [];
        iterator_to_array((new NewSearch($connection))->index('movies,logs')->lazy());
        $this->assertSame('/movies,logs/_search/point_in_time', $connection->paths[0]);
        $this->assertSame('keep_alive=1m&allow_partial_pit_creation=false', $connection->queries[0]);

        $connection->paths = [];
        (new ExistingScript('saved', $connection))->run('movies,logs*');
        $this->assertSame('/movies,logs*/_search/template', $connection->paths[0]);
    }

    /**
     * @test
     */
    public function a_template_param_keeps_an_integer_name(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            /** @var list<string> */
            public array $bodies = [];

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $this->bodies[] = (string) $request->getBody();

                return new Search(200, [], '{"hits":{"total":0,"hits":[]},"template_output":{"ok":true}}');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };

        $script = new ExistingScript('saved', $connection);
        $namedTen = json_decode('{"10":"ten","query":"hello"}', true);

        $script->run('movies', $namedTen);
        $script->render($namedTen);

        $this->assertSame('{"id":"saved","params":{"10":"ten","query":"hello"}}', $connection->bodies[0]);
        $this->assertSame($connection->bodies[0], $connection->bodies[1]);

        $connection->bodies = [];
        $script->run('movies', json_decode('{"1":"keep"}', true));
        $this->assertSame('{"id":"saved","params":{"1":"keep"}}', $connection->bodies[0]);

        $connection->bodies = [];
        $script->run('movies', [0 => 'a', 2 => 'c', 'query' => 'q']);
        $this->assertSame('{"id":"saved","params":{"0":"a","2":"c","query":"q"}}', $connection->bodies[0]);
    }

    /**
     * @test
     */
    public function an_integer_hit_total_is_the_count(): void
    {
        $integer = new Search(200, [], json_encode([
            'hits' => ['total' => 5, 'hits' => []],
        ]));
        $zero = new Search(200, [], json_encode([
            'hits' => ['total' => 0, 'hits' => []],
        ]));
        $object = new Search(200, [], json_encode([
            'hits' => ['total' => ['value' => 5, 'relation' => 'eq'], 'hits' => []],
        ]));

        $this->assertSame(5, $integer->total());
        $this->assertSame(0, $zero->total());
        $this->assertSame(5, $object->total());
    }

    /**
     * @test
     */
    public function an_empty_pit_index_stays_on_the_cluster_host(): void
    {
        $base = new Uri('https://localhost:9200');

        foreach ([new Elasticsearch, new Opensearch] as $engine) {
            $connection = new class($engine) implements ElasticsearchConnection
            {
                public array $uris = [];

                public function __construct(public SearchEngine $engine) {}

                public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
                {
                    $this->uris[] = $request->getUri();

                    return $request->response(new PsrResponse(200, [], '{}'));
                }

                public function promise(ElasticsearchRequest $request): Promise
                {
                    throw new RuntimeException('no cluster');
                }

                public function driver(): SearchEngine
                {
                    return $this->engine;
                }

                public function isServerless(): bool
                {
                    return false;
                }
            };

            (new PointInTimeRequests($connection))->open('', '1m');

            $uri = $connection->uris[0];
            $resolved = (string) UriResolver::resolve($base, $uri);
            $path = $engine instanceof Opensearch ? '/_search/point_in_time' : '/_pit';
            $query = $engine instanceof Opensearch
                ? 'keep_alive=1m&allow_partial_pit_creation=false'
                : 'keep_alive=1m';

            $this->assertSame('', $uri->getHost());
            $openPath = '/*'.$path;
            $this->assertSame($openPath, $uri->getPath());
            $this->assertSame($query, $uri->getQuery());
            $this->assertSame('https://localhost:9200'.$openPath.'?'.$query, $resolved);

            $connection->uris = [];
            (new PointInTimeRequests($connection))->open('movies', '1m');
            $this->assertSame('', $connection->uris[0]->getHost());
            $this->assertSame('/movies'.$path, $connection->uris[0]->getPath());
        }
    }

    /**
     * @test
     */
    public function an_exception_body_with_invalid_utf8_is_constructed(): void
    {
        $request = new HttpRequest('GET', new Uri('/movies'));
        $response = $request->response(new PsrResponse(502, [], "bad \xB1 bytes"));
        $exception = $response->exception($request);

        $this->assertInstanceOf(ElasticsearchException::class, $exception);
        $this->assertSame(502, $exception->getCode());
    }

    /**
     * @test
     */
    public function a_single_node_connection_error_keeps_the_curl_error(): void
    {
        $one = new MockHandler([
            new ConnectException(
                'cURL error 7: Failed to connect',
                new Request('GET', 'http://10.0.0.9:9200/'),
            ),
        ]);
        $sigmie = Sigmie::create(['http://10.0.0.9:9200'], config: [
            'handler' => $one,
        ]);

        try {
            $sigmie->indices();
            $this->fail('indices() returned');
        } catch (NoNodeAvailableException $noNodeAvailableException) {
            $previous = $noNodeAvailableException->getPrevious();
            $this->assertNotNull($previous);
            $this->assertStringContainsString('cURL error 7', $previous->getMessage());
        }

        $two = new MockHandler([
            new ConnectException(
                'cURL error 7: Failed to connect',
                new Request('GET', 'http://10.0.0.1:9200/'),
            ),
            new PsrResponse(200, [], '[]'),
        ]);
        $pair = Sigmie::create(
            ['http://10.0.0.1:9200', 'http://10.0.0.2:9200'],
            config: ['handler' => $two],
        );

        try {
            $pair->indices();
            $this->fail('indices() returned');
        } catch (NoNodeAvailableException $noNodeAvailableException) {
            $previous = $noNodeAvailableException->getPrevious();
            $this->assertNotNull($previous);
            $this->assertStringContainsString('cURL error 7', $previous->getMessage());
        }

        $this->assertSame(1, $two->count());
    }

    /**
     * @test
     */
    public function config_redirect_and_http_error_values_replace_the_defaults(): void
    {
        $defaults = null;
        $defaultHandler = static function ($request, array $options) use (&$defaults): PromiseInterface {
            $defaults = $options;

            return Create::promiseFor(
                new PsrResponse(200, ['Content-Type' => 'application/json'], '[]')
            );
        };
        Sigmie::create(['http://127.0.0.1:9200'], config: [
            'handler' => $defaultHandler,
        ])->indices();

        $this->assertFalse($defaults['allow_redirects']);
        $this->assertFalse($defaults['http_errors']);

        $overrides = null;
        $overrideHandler = static function ($request, array $options) use (&$overrides): PromiseInterface {
            $overrides = $options;

            return Create::promiseFor(
                new PsrResponse(200, ['Content-Type' => 'application/json'], '[]')
            );
        };
        Sigmie::create(['http://127.0.0.1:9200'], config: [
            'handler' => $overrideHandler,
            'allow_redirects' => true,
            'http_errors' => true,
        ])->indices();

        $this->assertTrue($overrides['allow_redirects'] === true || is_array($overrides['allow_redirects']));
        $this->assertTrue($overrides['http_errors']);
    }

    /**
     * @test
     */
    public function a_cloud_id_keeps_the_elasticsearch_port(): void
    {
        $withPort = 'different-es-kb-port:dXMtY2VudHJhbDEuZ2NwLmNsb3VkLmVzLmlvJGFjMzFlYmI5MDI0MTc3MzE1NzA0M2MzNGZkMjZmZDQ2OjkyNDMkYTRjMDYyMzBlNDhjOGZjZTdiZTg4YTA3NGEzYmIzZTA6OTI0NA==';
        $onHost = 'host-port:dXMtY2VudHJhbDEuZ2NwLmNsb3VkLmVzLmlvOjkyNDMkYWMzMWViYjkwMjQxNzczMTU3MDQzYzM0ZmQyNmZkNDYkYTRjMDYyMzBlNDhjOGZjZTdiZTg4YTA3NGEzYmIzZTA=';
        $plain = 'deploy:'.base64_encode('us-east-1.aws.found.io$abc$def');

        $this->assertSame(
            'https://ac31ebb90241773157043c34fd26fd46.us-central1.gcp.cloud.es.io:9243',
            $this->cloudHost($withPort)
        );
        $this->assertSame(
            'https://ac31ebb90241773157043c34fd26fd46.us-central1.gcp.cloud.es.io:9243',
            $this->cloudHost($onHost)
        );
        $this->assertSame('https://abc.us-east-1.aws.found.io', $this->cloudHost($plain));
    }

    private function cloudHost(string $cloudId): string
    {
        $sigmie = Sigmie::createForCloud($cloudId, 'your-encoded-api-key');
        $connection = (new ReflectionProperty($sigmie, 'elasticsearchConnection'))->getValue($sigmie);
        $jsonClient = (new ReflectionProperty($connection, 'http'))->getValue($connection);
        $transport = (new ReflectionProperty($jsonClient, 'http'))->getValue($jsonClient);
        $nodes = (new ReflectionProperty($transport->getNodePool(), 'nodes'))->getValue($transport->getNodePool());

        return (string) $nodes[0]->getUri();
    }

    private function capturingConnection(): ElasticsearchConnection
    {
        return new class implements ElasticsearchConnection
        {
            public string $path = '';

            public string $query = '';

            public string $fragment = '';

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $uri = $request->getUri();
                $this->path = $uri->getPath();
                $this->query = $uri->getQuery();
                $this->fragment = $uri->getFragment();

                return new Response(200, [], '[]');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };
    }

    public function test_unsigned_long_response_keeps_all_digits(): void
    {
        $response = new Response(200, [], '{"sort":[18446744073709551615,42]}');

        $this->assertSame(['18446744073709551615', 42], $response->json('sort'));
    }

    public function test_literal_dotted_index_in_exception_json(): void
    {
        $error = new ElasticsearchException(['failures' => ['logs.2024' => ['reason' => 'closed']]], 400);
        $this->assertSame('closed', $error->json('failures.logs.2024.reason'));
    }

    public function test_cleanup_includes_hidden_non_system_indices(): void
    {
        $queue = new MockHandler([
            function ($request): PsrResponse {
                parse_str($request->getUri()->getQuery(), $query);
                $this->assertSame('all', $query['expand_wildcards'] ?? null);

                return new PsrResponse(200, [], '[{"index":"hidden-catalog"},{"index":".system"}]');
            },
            function ($request): PsrResponse {
                $this->assertSame('DELETE /hidden-catalog', $request->getMethod().' '.$request->getUri()->getPath());

                return new PsrResponse(200, [], '{"acknowledged":true}');
            },
            new PsrResponse(200, [], '{}'),
            new PsrResponse(200, [], '{"acknowledged":true}'),
            new PsrResponse(200, [], '{"acknowledged":true}'),
        ]);
        $sigmie = Sigmie::create('http://localhost:9200', config: ['handler' => $queue]);
        $cleaner = new class
        {
            use ClearElasticsearch { clearElasticsearch as public; }
        };
        $cleaner->clearElasticsearch($sigmie->getElasticsearchConnection());
        $this->assertCount(0, $queue);
    }

    /** @dataProvider cleanupRaces */
    public function test_cleanup_continues_when_a_listed_resource_disappears(array $responses, array $expectedRequests): void
    {
        $queue = new MockHandler(array_map(
            fn (array $response): PsrResponse => new PsrResponse($response[0], [], json_encode($response[1], JSON_THROW_ON_ERROR)),
            $responses,
        ));
        $requests = [];
        $sigmie = Sigmie::create('http://localhost:9200', config: [
            'handler' => function ($request, array $options) use ($queue, &$requests): PromiseInterface {
                $requests[] = $request->getMethod().' '.$request->getUri()->getPath();

                return $queue($request, $options);
            },
        ]);
        $cleaner = new class
        {
            use ClearElasticsearch { clearElasticsearch as public; }
        };
        $cleaner->clearElasticsearch($sigmie->getElasticsearchConnection());

        $this->assertSame($expectedRequests, $requests);
    }

    public static function cleanupRaces(): array
    {
        $acknowledged = [200, ['acknowledged' => true]];
        $cat = 'GET /_cat/indices';
        $metadata = 'GET /_cluster/state/metadata';
        $templates = 'DELETE /_template/%2A';
        $pipelines = 'DELETE /_ingest/pipeline/%2A';

        return [
            'index' => [
                [
                    [200, [['index' => 'stale'], ['index' => 'remaining']]],
                    [404, ['error' => ['type' => 'index_not_found_exception', 'index' => 'stale']]],
                    $acknowledged,
                    [200, []],
                    $acknowledged,
                    $acknowledged,
                ],
                [$cat, 'DELETE /stale,remaining', 'DELETE /remaining', $metadata, $templates, $pipelines],
            ],
            'script' => [
                [
                    [200, []],
                    [200, ['metadata' => ['stored_scripts' => ['stale' => [], 'remaining' => []]]]],
                    [404, ['error' => ['type' => 'resource_not_found_exception']]],
                    $acknowledged,
                    $acknowledged,
                    $acknowledged,
                ],
                [$cat, $metadata, 'DELETE /_scripts/stale', 'DELETE /_scripts/remaining', $templates, $pipelines],
            ],
        ];
    }

    public function test_teardown_releases_the_test_clock_after_setup_fails(): void
    {
        $fixture = new class('test_fixture') extends SigmieTestCase
        {
            public function loadEnv(): void
            {
                Carbon::setTestNow('2000-01-01');

                throw new RuntimeException('Setup failed');
            }

            public function test_fixture(): void {}
        };

        try {
            $fixture->runBare();
            $this->fail('The fixture did not report the setup failure.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('Setup failed', $runtimeException->getMessage());
            $this->assertFalse(Carbon::hasTestNow());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_parallel_runner_stops_assigning_work_after_an_error(): void
    {
        $directory = sys_get_temp_dir().'/'.uniqid('sigmie-runner-');
        mkdir($directory);
        $first = $directory.'/FirstTest.php';
        $second = $directory.'/SecondTest.php';
        $log = $directory.'/results.xml';

        try {
            file_put_contents($first, <<<'PHP_WRAP'
<?php
final class FirstTest extends \PHPUnit\Framework\TestCase
{
    public function test_it_errors(): void
    {
        throw new \RuntimeException('First worker error');
    }
}
PHP_WRAP
            );
            file_put_contents($second, <<<'PHP_WRAP'
<?php
final class SecondTest extends \PHPUnit\Framework\TestCase
{
    public function test_it_passes(): void
    {
        self::assertTrue(true);
    }
}
PHP_WRAP
            );

            $process = new Process([
                PHP_BINARY, 'vendor/bin/paratest', '--runner='.ParallelRunner::class,
                '--processes=1', '--stop-on-error', '--no-coverage', '--log-junit='.$log, $directory,
            ], dirname(__DIR__));
            $this->assertSame(2, $process->run(), $process->getOutput().$process->getErrorOutput());
            $this->assertStringContainsString('First worker error', file_get_contents($log));
            $this->assertStringNotContainsString('SecondTest', file_get_contents($log));
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    public function test_async_http_errors_preserve_the_elasticsearch_response(): void
    {
        $sigmie = Sigmie::create('http://localhost:9200', config: [
            'http_errors' => true,
            'handler' => new MockHandler([new PsrResponse(404, [], '{"error":{"type":"index_not_found_exception","reason":"Missing movies"}}')]),
        ]);

        try {
            $sigmie->newQuery('movies')->matchAll()->promise()->wait();
            $this->fail('Expected an HTTP error.');
        } catch (HttpException $httpException) {
            $this->assertSame(404, $httpException->getCode());
            $this->assertStringContainsString('Missing movies', (string) $httpException->getResponse()->getBody());
        }
    }

    public function test_async_query_preserves_the_elasticsearch_error_through_the_default_client(): void
    {
        $sigmie = $this->withResponse([
            'error' => ['type' => 'index_not_found_exception', 'reason' => 'No index named missing'],
        ], 404);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('No index named missing');
        $sigmie->newQuery('missing')->matchAll()->promise()->wait();
    }

    public function test_response_reports_invalid_gzip(): void
    {
        $response = Response::fromPsrResponse(new PsrResponse(200, ['Content-Encoding' => 'gzip'], 'invalid gzip'));

        try {
            $response->json();
            $this->fail('Expected a gzip decoding exception.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(200, $elasticsearchException->getCode());
            $this->assertSame('invalid_gzip_response', $elasticsearchException->json('type'));
        }
    }

    public function test_create_for_cloud_rejects_a_missing_elasticsearch_id(): void
    {
        $this->expectException(CloudIdParseException::class);
        $this->expectExceptionMessage('Cloud ID not valid');

        Sigmie::createForCloud('deploy:'.base64_encode('example.com$'), 'test-key');
    }
}
