<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Uri;
use Http\Promise\Promise;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Http\Requests\MSearch;
use Sigmie\Base\Http\Requests\Search as SearchRequest;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\FilterParser;
use Sigmie\Query\Aggregations\Metrics\Sum;
use Sigmie\Query\Aggs;
use Sigmie\Query\BooleanQueryBuilder;
use Sigmie\Query\Contracts\Aggs as AggsContract;
use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\NewQuery;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\ElasticsearchKnn;
use Sigmie\Query\Queries\GeoDistance;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\OpenSearchKnn;
use Sigmie\Query\Queries\Raw;
use Sigmie\Query\Queries\Term\Fuzzy;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Term\Terms;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MatchBoolPrefix;
use Sigmie\Query\Queries\Text\MatchPhrase;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Query\Suggesters\Completion;
use Sigmie\Query\Suggesters\Phrase;
use Sigmie\Query\Suggesters\Term as TermSuggester;
use Sigmie\Search\InnerHits;
use Sigmie\Sigmie;

class QueryAddressedTest extends TestCase
{
    public function test_script_receives_floating_point_parameters_in_search_and_msearch(): void
    {
        $query = new FunctionScore(new MatchAll, 'params.x / 2', 'replace', ['x' => 1.0]);
        $body = ['query' => $query->toRaw()];
        $search = new SearchRequest('POST', new Uri('/movies/_search'), $body);
        $multi = new MSearch('POST', new Uri('/_msearch'), [['index' => 'movies'], $body]);

        foreach ([$search->body(), $multi->body()[1]] as $sent) {
            $this->assertSame(1.0, $sent['query']['function_score']['script_score']['script']['params']['x']);
        }
    }

    public function test_function_score_keeps_null_prefixed_parameter_names(): void
    {
        $params = ["\0key" => 3, 'key' => "\0key"];
        $query = new FunctionScore(new MatchAll, 'params.get(params.key)', 'replace', $params);
        $request = new SearchRequest('POST', new Uri('/movies/_search'), ['query' => $query->toRaw()]);

        $this->assertSame($params, $request->body()['query']['function_score']['script_score']['script']['params']);
    }

    public function test_failed_nested_bool_leaves_the_builder_unchanged(): void
    {
        $builder = (new BooleanQueryBuilder)->exists('status');
        $before = $builder->toRaw();

        try {
            $builder->bool(function (Boolean $query): void {
                $query->must()->term('status', 'draft');
                throw new RuntimeException('cancel clause');
            });
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('cancel clause', $runtimeException->getMessage());
        }

        $this->assertSame($before, $builder->toRaw());
    }

    public function test_raw_wildcard_preserves_the_numeric_token(): void
    {
        foreach (['1.20', '-0'] as $token) {
            $json = '{"wildcard":{"sku":{"value":'.$token.'}}}';
            $raw = (new Raw($json))->toRaw();
            $payload = isset($raw['wrapper']) ? base64_decode($raw['wrapper']['query']) : json_encode($raw);
            $this->assertSame($json, $payload);
        }
    }

    public function test_raw_script_accepts_params_deeper_than_php_decodes(): void
    {
        $json = '{"script":{"script":{"source":"true","params":{"nested":'
            .str_repeat('[', 515).'1'.str_repeat(']', 515).'}}}}';
        $body = (new Raw($json))->toRaw();

        $this->assertSame($json, base64_decode($body['wrapper']['query'] ?? '', true));
    }

    public function test_date_bounds_accept_both_input_formats(): void
    {
        $date = new DateTimeImmutable('2024-01-01T00:00:00Z');
        foreach ([['dd/MM/uuuu', '02/01/2024'], [null, 1704153600000]] as [$format, $upper]) {
            $range = new Range('day', ['>=' => $date, '<' => $upper], format: $format);
            $body = $range->toRaw()['range']['day'];
            $formats = explode('||', $body['format']);

            $this->assertContains(Range::ISO_DATE_FORMAT, $formats);
            $this->assertContains($format ?? 'epoch_millis', $formats);
            $this->assertSame($upper, $body['lt']);
        }
    }

    public function test_range_converts_offsets_beyond_eighteen_hours_to_utc(): void
    {
        $date = new DateTimeImmutable('2024-03-01T00:00:00.123400+19:00');
        $body = (new Range('day', ['>=' => $date]))->toRaw()['range']['day'];

        $this->assertSame('2024-02-29T05:00:00.123400+00:00', $body['gte']);
    }

    public function test_range_accepts_expanded_years(): void
    {
        $date = new DateTimeImmutable('+10000-01-01T00:00:00Z');
        $body = (new Range('day', ['>=' => $date]))->toRaw()['range']['day'];

        $this->assertSame('+10000-01-01T00:00:00.000000+00:00', $body['gte']);
        $this->assertSame("uuuu-MM-dd'T'HH:mm:ss.SSSSSSXXX", $body['format']);
    }

    public function test_date_histogram_accepts_the_lowest_epoch_millisecond(): void
    {
        $date = DateTimeImmutable::createFromFormat('U.u', '-9223372036854776.192000');
        $raw = (new Aggs)->dateHistogram('timeline', 'published', '1ms', extendedBounds: ['min' => $date])->toRaw();

        $this->assertSame(PHP_INT_MIN, $raw['timeline']['date_histogram']['extended_bounds']['min']);
    }

    public function test_boolean_serializes_each_clause_once(): void
    {
        $clause = $this->createMock(QueryClause::class);
        $raw = (new MatchAll)->toRaw();
        $clause->expects($this->once())->method('toRaw')->willReturn($raw);

        $boolean = new Boolean;
        $boolean->must()->query($clause);

        $this->assertSame([$raw], $boolean->toRaw()['bool']['must']);
    }

    public function test_reused_nested_clause_has_distinct_inner_hits_names(): void
    {
        $props = new NewProperties;
        $props->nested('reviews', fn (NewProperties $reviews): Text => $reviews->text('body'));

        $hits = new InnerHits;
        $hits->request(['reviews'], 2);
        $hits->resolve($props->get());

        $nested = new Nested('reviews', new MatchAll, innerHits: $hits);

        $query = new Boolean;
        $query->must()->query($nested);
        $query->filter()->query($nested);
        $body = $query->toRaw()['bool'];

        $this->assertNotSame($body['must'][0]['nested']['inner_hits']['name'], $body['filter'][0]['nested']['inner_hits']['name']);
    }

    /** @test */
    public function a_bool_term_preserves_a_fractional_number(): void
    {
        $body = (new NewQuery($this->connection(), 'products'))->bool(function (Boolean $query): void {
            $query->filter()->term('price', 12.5);
        })->toRaw();

        $this->assertSame(12.5, $body['query']['bool']['filter'][0]['term']['price']['value']);
    }

    /**
     * @test
     */
    public function range_fuzzy_and_geo_distance_keep_the_stored_boost(): void
    {
        $range = (new Range('price', ['>=' => 30]))->boost(2.5)->toRaw();
        $fuzzy = (new Fuzzy('title', 'abc'))->boost(3.5)->toRaw();
        $geo = (new GeoDistance('pin', '10km', '1', '2'))->boost(4.0)->toRaw();

        $this->assertSame(2.5, $range['range']['price']['boost']);
        $this->assertSame(3.5, $fuzzy['fuzzy']['title']['boost']);
        $this->assertSame(4.0, $geo['geo_distance']['boost']);
    }

    /**
     * @test
     */
    public function a_geo_field_named_distance_or_boost_keeps_the_parameter_and_the_point(): void
    {
        $distance = (new GeoDistance('distance', '10km', '51.49', '13.77'))->boost(4.0)->toRaw();
        $boost = (new GeoDistance('boost', '10km', '51.49', '13.77'))->boost(4.0)->toRaw();

        $this->assertSame('10km', $distance['geo_distance']['distance']);
        $this->assertSame('51.49', $distance['geo_distance']['distance.lat']);
        $this->assertSame('13.77', $distance['geo_distance']['distance.lon']);
        $this->assertSame(4.0, $distance['geo_distance']['boost']);

        $this->assertSame('10km', $boost['geo_distance']['distance']);
        $this->assertSame(4.0, $boost['geo_distance']['boost']);
        $this->assertSame('51.49', $boost['geo_distance']['boost.lat']);
        $this->assertSame('13.77', $boost['geo_distance']['boost.lon']);

        $properties = new NewProperties;
        $properties->geoPoint('distance');

        $parsed = (new FilterParser($properties))->parse('distance:10km[51.49,13.77]')->toRaw();

        $this->assertSame('10km', $parsed['bool']['must'][0]['geo_distance']['distance']);
        $this->assertSame('51.49', $parsed['bool']['must'][0]['geo_distance']['distance.lat']);
    }

    /**
     * @test
     */
    public function track_total_hits_false_and_zero_are_sent(): void
    {
        $search = $this->search();

        $this->assertArrayNotHasKey('track_total_hits', $search->toRaw());

        $this->assertFalse($search->trackTotalHits(false)->toRaw()['track_total_hits']);
        $this->assertSame(0, $this->search()->trackTotalHits(0)->toRaw()['track_total_hits']);
        $this->assertTrue($this->search()->trackTotalHits(true)->toRaw()['track_total_hits']);
        $this->assertSame(100, $this->search()->trackTotalHits(100)->toRaw()['track_total_hits']);
    }

    /**
     * @test
     */
    public function nested_bool_parse_sees_the_parent_properties(): void
    {
        $properties = new NewProperties;
        $properties->number('age');

        $outer = new Boolean($properties);
        $outer->must()->bool(function (Boolean $inner): void {
            $inner->must()->parse('age<21');
        });

        $raw = json_encode($outer->toRaw());

        $this->assertStringContainsString('"range"', $raw);
        $this->assertStringContainsString('"age"', $raw);
        $this->assertStringContainsString('"lt":"21"', $raw);
    }

    /**
     * @test
     */
    public function function_score_boost_is_stored(): void
    {
        $raw = (new FunctionScore(new MatchAll, 'return 1'))->boost(4.5)->toRaw();

        $this->assertSame(4.5, $raw['function_score']['boost']);
    }

    /**
     * @test
     */
    public function completion_analyzer_writes_the_given_name(): void
    {
        $raw = (new Completion('complete'))
            ->field('title')
            ->analyzer('folding')
            ->toRaw();

        $this->assertSame('folding', $raw['complete']['completion']['analyzer']);
        $this->assertSame('', $raw['complete']['prefix']);
    }

    /**
     * @test
     */
    public function completion_analyzer_zero_is_sent(): void
    {
        $raw = (new Completion('complete'))
            ->field('title')
            ->analyzer('0')
            ->toRaw();

        $this->assertSame('0', $raw['complete']['completion']['analyzer']);
        $this->assertSame('', $raw['complete']['prefix']);
    }

    /**
     * @test
     */
    public function suggester_text_zero_is_sent(): void
    {
        $term = (new TermSuggester('typos'))->field('title')->text('0')->toRaw();
        $phrase = (new Phrase('didyoumean'))->field('title')->text('0')->toRaw();
        $suggest = (new Suggest)->text('0')->toRaw();

        $this->assertSame('0', $term['typos']['text']);
        $this->assertSame('0', $phrase['didyoumean']['text']);
        $this->assertSame('0', $suggest['text']);
    }

    /**
     * @test
     */
    public function query_index_zero_is_stored(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                throw new RuntimeException('no cluster');
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

        $this->assertSame('0', (new NewQuery($connection, '0'))->matchAll()->index);
    }

    /**
     * @test
     */
    public function field_named_zero_stays_in_the_json_body(): void
    {
        $term = json_decode(json_encode((new Term('0', 'x'))->toRaw()), true);
        $match = json_decode(json_encode((new Match_('0', 'x'))->toRaw()), true);
        $suggest = new Suggest;
        $suggest->term('0')->field('title')->text('hi');
        $body = json_decode(json_encode($this->search()->suggest($suggest)->toRaw()), true);

        $this->assertSame('x', $term['term']['0']['value']);
        $this->assertSame('x', $match['match']['0']['query']);
        $this->assertSame('title', $body['suggest']['0']['term']['field']);
        $this->assertSame('hi', $body['suggest']['0']['text']);

        $only = new Aggs;
        $only->terms('0', 'genre');

        $onlyBody = json_decode(json_encode($this->search()->aggs($only)->toRaw()), true);

        $nested = new Aggs;
        $nested->terms('genres', 'genre')->aggregate(function (Aggs $inner): void {
            $inner->terms('0', 'brand');
        });
        $nestedBody = json_decode(json_encode($this->search()->aggs($nested)->toRaw()), true);

        $this->assertSame('genre', $onlyBody['aggs']['0']['terms']['field']);
        $this->assertSame('brand', $nestedBody['aggs']['genres']['aggs']['0']['terms']['field']);
    }

    /**
     * @test
     */
    public function an_added_raw_aggregation_named_zero_stays_an_object(): void
    {
        $aggs = json_encode($this->search()->addRaw('aggs', [
            '0' => ['max' => ['field' => 'brand']],
        ])->toRaw()['aggs']);

        $this->assertStringStartsWith('{"0":', $aggs);
    }

    /**
     * @test
     */
    public function terms_on_a_field_named_boost_keeps_the_values(): void
    {
        $raw = (new Terms('boost', ['horror', 'action']))->toRaw();

        $this->assertSame(['horror', 'action'], $raw['terms']['boost']);
    }

    /**
     * @test
     */
    public function a_raw_json_clause_is_the_query_object(): void
    {
        $builder = (new BooleanQueryBuilder)
            ->raw('{"term":{"status":"active"}}');

        $this->assertSame([
            ['term' => ['status' => 'active']],
        ], $builder->toRaw());
    }

    /**
     * @test
     */
    public function a_raw_field_named_zero_stays_an_object(): void
    {
        $json = '{"term":{"0":{"value":"active"}}}';

        $this->assertSame(
            '{"term":{"0":{"value":"active"}}}',
            json_encode((new Raw($json))->toRaw()),
        );
        $this->assertSame(
            '[{"term":{"0":{"value":"active"}}}]',
            json_encode((new BooleanQueryBuilder)->raw($json)->toRaw()),
        );
    }

    /**
     * @test
     */
    public function an_empty_raw_object_stays_an_object(): void
    {
        $this->assertSame(
            '{"match_all":{}}',
            json_encode((new Raw('{"match_all":{}}'))->toRaw()),
        );
        $this->assertSame(
            '[{"match_all":{}}]',
            json_encode((new BooleanQueryBuilder)->raw('{"match_all":{}}')->toRaw()),
        );
        $this->assertSame(
            '{"script":{"source":"return 1","params":{}}}',
            json_encode((new Raw('{"script":{"source":"return 1","params":{}}}'))->toRaw()),
        );
    }

    /**
     * @test
     */
    public function a_raw_object_keyed_from_zero_stays_an_object(): void
    {
        $this->assertSame(
            '{"0":"kept"}',
            json_encode((new Raw('{"0":"kept"}'))->toRaw()),
        );
        $this->assertSame(
            '{"0":"a","1":"b"}',
            json_encode((new Raw('{"0":"a","1":"b"}'))->toRaw()),
        );
        $this->assertSame(
            '{}',
            json_encode((new Raw('{}'))->toRaw()),
        );
        $this->assertSame(
            '[{"0":"kept"}]',
            json_encode((new BooleanQueryBuilder)->raw('{"0":"kept"}')->toRaw()),
        );
        $this->assertSame(
            '{"term":{"status":"active"}}',
            json_encode((new Raw('{"term":{"status":"active"}}'))->toRaw()),
        );
    }

    /**
     * @test
     */
    public function an_aggs_interface_reaches_the_search_body(): void
    {
        $aggs = new class implements AggsContract
        {
            public function min(string $name, string $field): void {}

            public function max(string $name, string $field): void {}

            public function avg(string $name, string $field): void {}

            public function toRaw(): array
            {
                return ['revenue' => ['avg' => ['field' => 'amount']]];
            }
        };

        $search = (new Sigmie($this->connection()))->query('movies', aggs: $aggs);
        $body = json_decode(json_encode($search->toRaw()), true);

        $this->assertSame('movies', $search->index);
        $this->assertSame(
            ['revenue' => ['avg' => ['field' => 'amount']]],
            $body['aggs'],
        );
    }

    /**
     * @test
     */
    public function a_search_raw_key_named_one_stays(): void
    {
        $search = $this->search();
        $search->addRaw('1', 'kept');
        $search->addRaw('2', 'also');
        $search->addRaw('collapse', ['field' => 'brand']);

        $json = json_encode($search->toRaw());

        $this->assertStringContainsString('"1":"kept"', $json);
        $this->assertStringContainsString('"2":"also"', $json);
        $this->assertStringContainsString('"collapse":{"field":"brand"}', $json);

        $zero = $this->search();
        $zero->addRaw('0', 'kept');

        $this->assertStringContainsString('"0":"kept"', json_encode($zero->toRaw()));
    }

    /**
     * @test
     */
    public function a_bool_raw_key_named_one_stays(): void
    {
        $bool = new Boolean;
        $bool->addRaw('1', 'kept');
        $bool->addRaw('minimum_should_match', 1);

        $json = json_encode($bool->toRaw());

        $this->assertStringContainsString('"1":"kept"', $json);
        $this->assertStringContainsString('"minimum_should_match":1', $json);
    }

    /**
     * @test
     */
    public function completion_uses_global_text_unless_its_prefix_was_set(): void
    {
        $suggest = (new Suggest)->text('sta');
        $suggest->completion('places')->field('title');
        $this->assertSame('sta', $suggest->toRaw()['places']['prefix']);
        $suggest->completion('text')->field('title');
        $suggest->completion('explicit')->field('title')->prefix('');
        $raw = $suggest->toRaw();

        $this->assertSame('sta', $raw['text']['prefix']);
        $this->assertSame('', $raw['explicit']['prefix']);
        $suggest->text('star');
        $this->assertSame('star', $suggest->toRaw()['text']['prefix']);
    }

    /** @test */
    public function a_suggester_named_text_keeps_the_term_clause(): void
    {
        $named = new Suggest;
        $named->text('helo');
        $named->term('text')->field('text');

        $this->assertSame(
            '{"text":{"term":{"field":"text","size":"5","sort":"score","suggest_mode":"always"},"text":"helo"}}',
            json_encode($named->toRaw())
        );

        $withTitle = new Suggest;
        $withTitle->text('helo');
        $withTitle->term('text')->field('text');
        $withTitle->term('title')->field('title');
        $both = $withTitle->toRaw();

        $this->assertSame('text', $both['text']['term']['field']);
        $this->assertSame('helo', $both['text']['text']);
        $this->assertSame('title', $both['title']['term']['field']);
        $this->assertSame('helo', $both['title']['text']);

        $plain = new Suggest;
        $plain->text('helo');
        $plain->term('title')->field('title');
        $plainRaw = $plain->toRaw();

        $this->assertSame('helo', $plainRaw['text']);
        $this->assertArrayNotHasKey('text', $plainRaw['title']);

        $bare = new Suggest;
        $bare->term('text')->field('text');
        $bareRaw = $bare->toRaw();

        $this->assertSame('text', $bareRaw['text']['term']['field']);
        $this->assertArrayNotHasKey('text', $bareRaw['text']);

        $own = new Suggest;
        $own->text('helo');
        $own->term('text')->field('text')->text('mine');
        $own->term('title')->field('title');
        $ownRaw = $own->toRaw();

        $this->assertSame('mine', $ownRaw['text']['text']);
        $this->assertSame('helo', $ownRaw['title']['text']);

        $phrase = new Suggest;
        $phrase->text('helo');
        $phrase->phrase('text')->field('body');
        $phraseRaw = $phrase->toRaw();

        $this->assertSame('body', $phraseRaw['text']['phrase']['field']);
        $this->assertSame('helo', $phraseRaw['text']['text']);

        $completion = new Suggest;
        $completion->text('helo');
        $completion->completion('text')->field('title')->prefix('he');
        $completion->term('other')->field('title');
        $completionRaw = $completion->toRaw();

        $this->assertSame('he', $completionRaw['text']['prefix']);
        $this->assertArrayNotHasKey('text', $completionRaw['text']);
        $this->assertSame('helo', $completionRaw['other']['text']);

        $zero = new Suggest;
        $zero->text('0');
        $zero->term('text')->field('title');
        $zeroRaw = $zero->toRaw();

        $this->assertSame('0', $zeroRaw['text']['text']);
        $this->assertSame('title', $zeroRaw['text']['term']['field']);
    }

    /**
     * @test
     */
    public function a_nested_query_ignores_an_inner_hit_path_named_zero(): void
    {
        $props = new NewProperties;
        $props->nested('0', function (NewProperties $props): void {
            $props->keyword('child');
        });
        $props->nested('reviews', function (NewProperties $props): void {
            $props->text('comment');
        });

        $hits = new InnerHits;
        $hits->request(['0'], 5);
        $hits->resolve($props->get());

        $this->assertSame(
            '{"nested":{"path":"reviews","score_mode":"avg","query":{"match_all":{"boost":1}},"boost":1}}',
            json_encode((new Nested('reviews', new MatchAll, innerHits: $hits))->toRaw())
        );

        $onZero = json_encode((new Nested('0', new MatchAll, innerHits: $hits))->toRaw());

        $this->assertStringContainsString('"path":"0"', $onZero);
        $this->assertStringContainsString('inner_hits', $onZero);
    }

    public function test_geo_distance_accepts_numeric_coordinates(): void
    {
        $raw = (new GeoDistance('pin', '10km', 51.49, 13.77))->toRaw();

        $this->assertSame(['lat' => 51.49, 'lon' => 13.77], $raw['geo_distance']['pin']);
    }

    public function test_unfiltered_opensearch_knn_omits_filter(): void
    {
        $raw = (new OpenSearchKnn('vector', [1, 2], 1, []))->toRaw();

        $this->assertArrayNotHasKey('filter', $raw['knn']['vector']);
    }

    public function test_phrase_suggester_uses_the_fields_shingle_size(): void
    {
        $suggest = new Suggest;
        $phrase = $suggest->phrase('spell')->field('title')->text('nobell peace price');

        $this->assertArrayNotHasKey('gram_size', $suggest->toRaw()['spell']['phrase']);
        $phrase->ngramSize(1);
        $this->assertSame(1, $suggest->toRaw()['spell']['phrase']['gram_size']);
    }

    public function test_raw_unsigned_long_keeps_its_exact_numeric_value(): void
    {
        $json = '{"term":{"sequence":18446744073709551615}}';
        $raw = (new Raw($json))->toRaw();

        $this->assertSame($json, base64_decode($raw['wrapper']['query'], true));
    }

    public function test_raw_clause_boost_scales_its_score(): void
    {
        $query = new Raw('{"match":{"brand":"Apple"}}');
        $raw = $query->boost(3)->toRaw();

        $this->assertSame(3.0, $raw['bool']['boost']);
        $this->assertSame(['match' => ['brand' => 'Apple']], $raw['bool']['must']);
    }

    public function test_terms_boost_on_a_field_named_boost(): void
    {
        $raw = (new Terms('boost', ['a']))->boost(4)->toRaw();

        $this->assertSame(4.0, $raw['bool']['boost']);
        $this->assertSame(['terms' => ['boost' => ['a']]], $raw['bool']['must']);
    }

    public function test_default_text_queries_use_the_mapped_analyzer(): void
    {
        $builder = new NewQuery($this->connection());
        $match = $builder->match('brand', 'New York')->toRaw()['query'];
        $multi = $builder->multiMatch(['brand'], 'New York')->toRaw()['query'];

        $this->assertArrayNotHasKey('analyzer', $match['match']['brand']);
        $this->assertArrayNotHasKey('analyzer', $multi['multi_match']);

        foreach ([MatchBoolPrefix::class, MatchPhrase::class, MatchPhrasePrefix::class] as $type) {
            $query = (new $type('brand', 'New York'))->toRaw();
            $this->assertArrayNotHasKey('analyzer', reset($query)['brand']);
        }

        $explicit = $builder->match('brand', 'New York', analyzer: 'default')->toRaw()['query'];
        $this->assertSame('default', $explicit['match']['brand']['analyzer']);
    }

    public function test_filtered_query_values_encode_as_lists(): void
    {
        $builder = new BooleanQueryBuilder;
        [$ids, $multi, $terms] = $builder->ids([2 => 'one'])
            ->multiMatch([2 => 'brand'], 'a')
            ->terms('brand', [2 => 'a'])
            ->toRaw();

        $this->assertSame(['one'], $ids['ids']['values']);
        $this->assertSame(['brand'], $multi['multi_match']['fields']);
        $this->assertSame(['a'], $terms['terms']['brand']);

        $lookup = ['id' => 'one', 'index' => 'catalog', 'path' => 'brand'];
        $this->assertSame($lookup, (new Terms('brand', $lookup))->toRaw()['terms']['brand']);
    }

    public function test_raw_script_retains_floating_point_division(): void
    {
        $json = '{"script":{"script":{"source":"params.x / 2 > 0","params":{"x":1.0}}}}';
        $raw = (new Raw($json))->toRaw();
        $payload = isset($raw['wrapper']) ? base64_decode($raw['wrapper']['query']) : json_encode($raw);

        $this->assertSame(1.0, json_decode($payload, true)['script']['script']['params']['x']);
    }

    public function test_raw_script_accepts_a_null_prefixed_parameter_name(): void
    {
        $json = '{"script":{"script":{"source":"params.values().iterator().next() == 1","params":{"\u0000key":1}}}}';
        $raw = (new Raw($json))->boost(2)->toRaw();
        $query = $raw['bool']['must'];
        $payload = isset($query['wrapper']) ? base64_decode($query['wrapper']['query']) : json_encode($query);

        $this->assertSame(1, json_decode($payload, true)['script']['script']['params']["\0key"]);
        $this->assertSame(2.0, $raw['bool']['boost']);
    }

    public function test_knn_vectors_keep_components_in_iteration_order(): void
    {
        $vector = [7 => 1, 9 => 0];
        $elastic = (new ElasticsearchKnn('vec', $vector, 1, 2, []))->toRaw();
        $open = (new OpenSearchKnn('vec', $vector, 1, []))->toRaw();

        $this->assertSame([1, 0], $elastic['knn']['query_vector']);
        $this->assertSame([1, 0], $open['knn']['vec']['vector']);
    }

    public function test_knn_filter_list_survives_removing_a_clause(): void
    {
        $filters = [1 => ['term' => ['brand' => 'apple']]];
        $knn = new ElasticsearchKnn('vec', [1, 0], 1, 2, $filters);

        $this->assertSame(array_values($filters), $knn->toRaw()['knn']['filter']);
    }

    private function search(): Search
    {
        return new Search($this->connection());
    }

    private function connection(): ElasticsearchConnection
    {
        return new class implements ElasticsearchConnection
        {
            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                throw new RuntimeException('no cluster');
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
    }

    public function test_source_filters_encode_as_lists(): void
    {
        $raw = $this->search()->fields([3 => 'title'])->except([2 => 'secret'])->toRaw();
        $source = json_decode(json_encode($raw))->_source;
        $this->assertSame(['title'], $source->includes);
        $this->assertSame(['secret'], $source->excludes);
    }

    /** @test */
    public function reused_new_query_keeps_previous_searches_and_their_options_separate(): void
    {
        $builder = (new NewQuery($this->connection(), 'orders'))->sort([['price' => 'asc']]);
        $first = $builder->term('status', 'paid')->size(3);
        $first->aggregate(fn (Aggs $aggs): Sum => $aggs->sum('total', 'price'));

        $firstRaw = $first->getDSL();
        $second = $builder->term('status', 'open');

        $this->assertSame($firstRaw, $first->getDSL());
        $this->assertSame('open', $second->getDSL()['query']['term']['status']['value']);
        $this->assertSame(500, $second->getDSL()['size']);
        $this->assertArrayNotHasKey('aggs', $second->getDSL());
        $this->assertSame([['price' => 'asc']], $second->getDSL()['sort']);
        $this->assertSame($second->getDSL(), $builder->toMultiSearch()[1]);
    }

    public function test_range_queries_serialize_php_date_bounds_as_instants(): void
    {
        $bound = new DateTimeImmutable('2040-02-29T12:34:56.123456+05:45');
        $raw = (new NewQuery($this->connection(), 'events'))->range('created_at', ['>=' => $bound])->toRaw();

        $this->assertSame('2040-02-29T12:34:56.123456+05:45', $raw['query']['range']['created_at']['gte']);
        $this->assertSame('strict_date_optional_time_nanos', $raw['query']['range']['created_at']['format']);
    }
}
