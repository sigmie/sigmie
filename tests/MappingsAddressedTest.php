<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\Drivers\Opensearch;
use Sigmie\Document\Document;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Analysis\TokenFilter\Trim;
use Sigmie\Index\Index as RawIndex;
use Sigmie\Index\Mappings;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Field;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\NewSemanticField;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\Boolean as BooleanField;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Category;
use Sigmie\Mappings\Types\Combo;
use Sigmie\Mappings\Types\Date as DateField;
use Sigmie\Mappings\Types\DateTime as DateTimeField;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Email;
use Sigmie\Mappings\Types\FlatObject;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\HTML;
use Sigmie\Mappings\Types\Id;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\KnnVector;
use Sigmie\Mappings\Types\LongText;
use Sigmie\Mappings\Types\Name;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\SearchableNumber;
use Sigmie\Mappings\Types\ShortText;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Compound\Boolean as BooleanQuery;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Semantic\DocumentProcessor;
use Sigmie\Semantic\Providers\Noop;
use Sigmie\Testing\Assert;
use UnexpectedValueException;

class MappingsAddressedTest extends TestCase
{
    public function test_flattened_keys_cannot_contain_the_key_value_separator(): void
    {
        $field = new FlatObject('f');
        $this->assertFalse($field->validate('f', ['a' => [["b\0c" => 1]]])[0]);
        $this->assertTrue($field->validate('f', ['a' => "b\0c"])[0]);
        $this->assertTrue($field->validate('f', ["b\0c" => 1], SearchEngineType::OpenSearch)[0]);
    }

    public function test_flattened_terms_include_the_full_key_and_separator(): void
    {
        $field = new FlatObject('f');
        $this->assertFalse($field->validate('f', ['a' => ['b' => str_repeat('x', 32763)]])[0]);
        $this->assertTrue($field->validate('f', ['a' => ['b' => str_repeat('x', 32762)]])[0]);
    }

    public function test_flattened_depth_counts_objects_inside_arrays(): void
    {
        $props = Properties::create(['f' => ['type' => 'flattened', 'depth_limit' => 2]], new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $this->assertFalse($props['f']->validate('f', ['a' => [['b' => ['c' => 1]]]])[0]);
        $this->assertTrue($props['f']->validate('f', ['a' => [['b' => 1]]])[0]);
    }

    public function test_floating_ranges_require_a_representable_value(): void
    {
        foreach (['float' => 1.0000001192092896, 'double' => 1.0000000000000002] as $type => $next) {
            $field = (new Range('r'))->$type();
            $this->assertFalse($field->validate('r', ['gt' => 1, 'lt' => $next])[0]);
            $this->assertTrue($field->validate('r', ['gte' => 1, 'lt' => $next])[0]);
        }
    }

    public function test_fractional_epoch_ranges_compare_indexed_milliseconds(): void
    {
        $range = (new Range('r'))->date();
        $this->assertFalse($range->validate('r', ['gte' => '0.1', 'lt' => '0.9'])[0]);
        $this->assertTrue($range->validate('r', ['gte' => '-0.1', 'lt' => '0'])[0]);
    }

    public function test_scoped_ipv6_addresses_are_valid_range_bounds(): void
    {
        $range = (new Range('r'))->ip();
        $this->assertTrue($range->validate('r', ['gte' => 'fe80::1%eth0', 'lte' => 'fe80::2'])[0]);
        $this->assertFalse($range->validate('r', ['gte' => 'fe80::1%'])[0]);
    }

    public function test_flat_object_lists_keep_numeric_object_members(): void
    {
        $props = new Properties(fields: ['f' => new FlatObject('f')]);
        $this->assertSame('{"f":[{"0":3}]}', json_encode($props->sourceObject(['f' => [[3]]]), JSON_THROW_ON_ERROR));
    }

    public function test_strict_object_records_reject_unmapped_keys(): void
    {
        $fields = Properties::create(['obj' => ['type' => 'nested', 'dynamic' => 'strict', 'properties' => ['s' => ['type' => 'text']]]], new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $field = $fields['obj'];
        $this->assertFalse($field->validate('obj', [(object) ['unknown' => null]])[0]);
        $this->assertTrue($field->validate('obj', [(object) ['s' => 'known']])[0]);
    }

    public function test_geo_shape_requires_a_geometry(): void
    {
        $field = Properties::create(['g' => ['type' => 'geo_shape']], new DefaultAnalyzer, [], Properties::ROOT_NAME)['g'];
        foreach ([1, 'bad', 'POINT (bad)', ['type' => 'Point', 'coordinates' => ['1', '2']], ['type' => 'Triangle', 'coordinates' => []], ['lat' => 1, 'lon' => 2]] as $invalid) {
            $this->assertFalse($field->validate('g', $invalid)[0]);
        }

        $this->assertTrue($field->validate('g', ['type' => 'Point', 'coordinates' => [1, 2]])[0]);
        $this->assertTrue($field->validate('g', 'POINT (1 2)')[0]);
    }

    public function test_image_sources_reject_json_objects(): void
    {
        $this->assertFalse((new Image('photo'))->validate('photo', ['url' => 'photo.jpg'])[0]);
    }

    public function test_wkt_z_requires_three_coordinates_and_a_separate_token(): void
    {
        $point = new GeoPoint('p');
        foreach (['POINT Z (1 2)', 'POINTZ (1 2 3)'] as $value) {
            $this->assertFalse($point->validate('p', $value)[0]);
        }

        $this->assertTrue($point->validate('p', 'POINT Z (1 2 3)')[0]);
    }

    public function test_keyword_limits_apply_to_the_encoded_term_and_multifields(): void
    {
        $word = str_repeat('é', 16384);
        foreach ([new Keyword('k'), new CaseSensitiveKeyword('k'), (new Text('k'))->keyword()] as $field) {
            $this->assertFalse($field->validate('k', $word)[0]);
            $this->assertTrue($field->validate('k', substr($word, 2))[0]);
        }

        $field = Properties::create(['k' => ['type' => 'keyword', 'ignore_above' => 9000]], new DefaultAnalyzer, [], Properties::ROOT_NAME)['k'];
        $this->assertTrue($field->validate('k', str_repeat('😀', 8500))[0]);
    }

    public function test_epoch_only_fields_reject_iso_fallback(): void
    {
        foreach ([new DateField('d', ['epoch_second']), new DateTimeField('d', ['epoch_millis'])] as $field) {
            $this->assertFalse($field->validate('d', '2024-02-29')[0]);
            $this->assertFalse($field->validate('d', '2024-02-29T12:00:00Z')[0]);
        }
    }

    public function test_epoch_values_fit_the_parser_precision_and_storage_range(): void
    {
        $ms = new DateField('d', ['epoch_millis']);
        $sec = new DateField('d', ['epoch_second']);
        foreach (['+1', '1.1234567', '00000000000000000001', '9223372036854775808'] as $value) {
            $this->assertFalse($ms->validate('d', $value)[0]);
        }

        $this->assertTrue($ms->validate('d', '1.123456')[0]);
        $this->assertFalse($sec->validate('d', '9223372036854775.808')[0]);
        $this->assertTrue($sec->validate('d', '9223372036854775.8079')[0]);
    }

    public function test_calendar_patterns_validate_calendar_dates(): void
    {
        $date = new DateField('d', ['MM/dd/yyyy']);
        $this->assertFalse($date->validate('d', '02/29/2023')[0]);
        $this->assertFalse($date->validate('d', '2024-02-29')[0]);
        $this->assertTrue($date->validate('d', '02/29/2024')[0]);
        $this->assertTrue($date->validate('d', '02/29/+10000')[0]);
    }

    public function test_calendar_only_fields_write_php_dates_in_the_configured_pattern(): void
    {
        $field = new DateField('d', ['MM/dd/yyyy']);
        $this->assertSame('02/29/2024', $field->formatForWrite(new DateTime('2024-02-29'), 'Y-m-d'));
    }

    public function test_ip_ranges_contain_at_least_one_address(): void
    {
        $field = (new Range('r'))->ip();
        foreach ([['gt' => '::2', 'lt' => '::3'], ['gte' => '::3', 'lte' => '::2'], ['lt' => '::']] as $bounds) {
            $this->assertFalse($field->validate('r', $bounds)[0]);
        }

        $this->assertTrue($field->validate('r', ['gte' => '192.0.2.1', 'lte' => '::ffff:c000:201'])[0]);
    }

    public function test_date_range_bounds_validate_date_math_operations(): void
    {
        $field = (new Range('r'))->date();
        $this->assertFalse($field->validate('r', ['gte' => '2010-02-03||+1Q'])[0]);
        $this->assertTrue($field->validate('r', ['gte' => '2010-02-03||+1d/d'])[0]);
    }

    public function test_date_range_bounds_validate_the_iso_time_component(): void
    {
        $field = (new Range('r'))->date();
        $this->assertFalse($field->validate('r', ['gte' => '2022-02-28Tbad'])[0]);
        $this->assertTrue($field->validate('r', ['gte' => '2010-02-03T15:00Z'])[0]);
    }

    public function test_exclusive_calendar_date_ranges_must_contain_a_day(): void
    {
        $field = (new Range('r'))->date();
        $this->assertFalse($field->validate('r', ['gt' => '2024-02-28', 'lt' => '2024-02-29'])[0]);
        $this->assertTrue($field->validate('r', ['gt' => '2024-02-28', 'lte' => '2024-02-29'])[0]);
    }

    public function test_mapped_ip_values_require_an_address(): void
    {
        $field = Properties::create(['addr' => ['type' => 'ip']], new DefaultAnalyzer, [], Properties::ROOT_NAME)['addr'];
        $this->assertFalse($field->validate('addr', 'bad')[0]);
        $this->assertTrue($field->validate('addr', '2001:db8::1')[0]);
    }

    public function test_ignore_malformed_preserves_invalid_scalars_in_the_document(): void
    {
        $fields = Properties::create(['n' => ['type' => 'integer', 'ignore_malformed' => true]], new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $writer = new DocumentProcessor($fields);
        $doc = new Document(['n' => [false, 'bad', 2]]);
        $this->assertSame($doc, $writer->validateFields($doc));
        $this->expectException(InvalidArgumentException::class);
        $writer->validateFields(new Document(['n' => ['bad' => 1]]));
    }

    public function test_dynamic_template_keeps_its_numeric_name_and_empty_runtime_definition(): void
    {
        $templates = '[{"0":{"match_mapping_type":"long","runtime":{}}}]';
        $index = RawIndex::fromRaw('books', ['mappings' => [
            'dynamic_templates' => json_decode($templates, true, flags: JSON_THROW_ON_ERROR),
        ]]);

        $this->assertSame($templates, json_encode($index->mappings->toRaw(new Elasticsearch)['dynamic_templates'], JSON_THROW_ON_ERROR));
    }

    public function test_implicit_text_field_uses_the_restored_default_search_analyzer(): void
    {
        $index = RawIndex::fromRaw('books', [
            'settings' => ['index' => ['analysis' => ['analyzer' => ['default_search' => ['type' => 'keyword']]]]],
            'mappings' => ['properties' => [
                'title' => ['type' => 'text'],
                'body' => ['type' => 'text', 'analyzer' => 'simple'],
            ]],
        ]);

        $fields = $index->mappings->properties();
        $this->assertSame('default_search', $fields['title']->searchAnalyzer());
        $this->assertSame('simple', $fields['body']->searchAnalyzer());
    }

    public function test_adding_a_numeric_subfield_to_a_clone_keeps_existing_fields(): void
    {
        $original = (new Text('body'))->field(new Keyword('exact'));
        $clone = clone $original;
        $clone->field(new Keyword('0'));

        $this->assertSame(['exact', 0], array_keys((array) $clone->toRaw()['body']['fields']));
    }

    public function test_copy_targets_with_sparse_keys_encode_as_a_list(): void
    {
        $type = (new Text('title'))->copyTo([2 => 'search', 4 => 'suggest']);

        $this->assertSame('["search","suggest"]', json_encode($type->toRaw()['title']['copy_to']));
    }

    public function test_selecting_keyword_keeps_a_restored_exact_subfield(): void
    {
        $text = Text::fromRaw(['body' => ['type' => 'text', 'fields' => ['exact' => ['type' => 'keyword']]]]);
        $text->keyword();

        $this->assertSame(['type' => 'keyword'], $text->toRaw()['body']['fields']['exact'] ?? null);
    }

    public function test_replacing_the_selected_keyword_updates_filterability(): void
    {
        $text = new Text('body', 'exact');
        $text->field(new Text('keyword'));
        $this->assertSame('body.exact', $text->filterableName());
        $text->field(new Text('exact'));
        $this->assertNull($text->filterableName());
    }

    public function test_enabling_keyword_replaces_a_text_subfield_of_that_name(): void
    {
        foreach (['keyword', 'facetSearchable'] as $method) {
            $text = (new Text('body'))->field(new Text('keyword'));
            $text->$method();

            $this->assertSame('keyword', $text->toRaw()['body']['fields']['keyword']['type']);
            $this->assertSame('body.keyword', $text->filterableName());
        }
    }

    public function test_text_presets_query_the_mapped_keyword_subfield(): void
    {
        foreach ([Category::class, Email::class] as $preset) {
            foreach ([['exact' => ['type' => 'keyword']], []] as $fields) {
                $field = $preset::fromRaw(['field' => ['type' => 'text', 'fields' => $fields]]);
                $terms = [];
                foreach ($field->queries('Example') as $query) {
                    $terms += $query->toRaw()['term'] ?? [];
                }

                $this->assertSame($fields === [] ? [] : ['field.exact'], array_keys($terms));
            }
        }
    }

    public function test_boolean_query_string_clauses_accept_multiple_queries(): void
    {
        $field = new BooleanField('active');
        $queries = $field->queryStringQueries(['ignored', ' ACTIVE ']);

        $this->assertCount(1, $queries);
        $this->assertTrue($queries[0]->toRaw()['term']['active']['value']);
    }

    public function test_exact_vector_scores_only_nested_children_with_a_vector(): void
    {
        foreach ([new Elasticsearch, new Opensearch] as $driver) {
            $field = $driver->nestedVectorField(new NestedVector('chunks', 2));
            $query = $field->vectorQueries([1.0, 0.0], 1, new BooleanQuery)[0]->toRaw();

            $this->assertSame(
                '_embeddings.chunks.vector',
                $query['nested']['query']['function_score']['query']['exists']['field'] ?? null,
            );
        }
    }

    public function test_unnormalized_indexed_dot_product_accepts_non_unit_vectors(): void
    {
        $field = (new NewSemanticField('title'))->accuracy(1, 2)
            ->dotProductSimilarity()->normalizeVector(false)->make();
        $mapping = (new Elasticsearch)->vectorField($field)->toRaw()[$field->name];

        $this->assertSame('max_inner_product', $mapping['similarity']);
    }

    public function test_geographic_point_lists_accept_missing_entries(): void
    {
        $props = new NewProperties;
        $props->geoPoint('location');

        $document = new Document(['location' => [null, ['lat' => 48.8, 'lon' => 2.4], null]]);

        $this->assertSame($document, (new DocumentProcessor($props->get()))->validateFields($document));
    }

    public function test_date_fields_accept_negative_iso_years(): void
    {
        $props = new NewProperties;
        $props->date('published');
        $props->datetime('created');

        $processor = new DocumentProcessor($props->get());
        $document = $processor->formatDateTimeFields(new Document([
            'published' => new DateTime('-0004-02-29'),
            'created' => new DateTime('-0001-01-02T12:30:00Z'),
        ]));

        $this->assertSame('-0004-02-29', $document->_source['published']);
        $this->assertSame('-0001-01-02T12:30:00.000000+00:00', $document->_source['created']);
        $this->assertSame($document, $processor->validateFields($document));
        $this->assertFalse($props->get()->get('published')->validate('published', '-0001-02-29')[0]);
    }

    public function test_restoring_a_keyword_keeps_its_native_normalization_and_options(): void
    {
        $raw = [
            'sku' => ['type' => 'keyword', 'ignore_above' => 80, 'fields' => ['0' => ['type' => 'keyword']]],
            'tag' => ['type' => 'keyword', 'normalizer' => 'my_normalizer', 'doc_values' => false],
        ];
        $mapping = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME)->toRaw();

        $this->assertArrayNotHasKey('normalizer', $mapping['sku']);
        $this->assertSame(80, $mapping['sku']['ignore_above'] ?? null);
        $this->assertSame('{"0":{"type":"keyword"}}', json_encode($mapping['sku']['fields'] ?? null));
        $this->assertSame('my_normalizer', $mapping['tag']['normalizer'] ?? null);
        $this->assertFalse($mapping['tag']['doc_values'] ?? true);
    }

    public function test_direct_vector_restore_preserves_and_updates_metadata(): void
    {
        $vectors = [
            DenseVector::fromMapping('v', ['type' => 'dense_vector', 'dims' => 2, 'meta' => ['source' => 'old']]),
            KnnVector::fromMapping('v', ['type' => 'knn_vector', 'dimension' => 2, 'meta' => ['source' => 'old']]),
        ];

        foreach ($vectors as $vector) {
            $this->assertSame(['source' => 'old'], $vector->getMeta());
            $vector->meta(['source' => 'new', 'owner' => 'catalog']);
            $this->assertSame(['source' => 'new', 'owner' => 'catalog'], $vector->toRaw()['v']['meta']);
        }
    }

    public function test_opensearch_keeps_metadata_when_converting_a_builder_vector(): void
    {
        $props = new NewProperties;
        $props->vector('v', 2)->meta(['0' => 'catalog']);
        $mapping = $props->get()->toRaw(new Opensearch);

        $this->assertSame('{"0":"catalog"}', json_encode($mapping['v']['meta'] ?? null));
    }

    public function test_opensearch_knn_caps_the_requested_window(): void
    {
        $field = new KnnVector('v', dims: 2);

        foreach ([20 => 20, 10001 => 10000] as $requested => $expected) {
            $query = $field->vectorQueries([1.0, 0.0], $requested, new BooleanQuery)[0]->toRaw();
            $this->assertSame($expected, $query['knn']['_embeddings.v']['k']);
        }
    }

    public function test_geographic_point_objects_pass_document_validation(): void
    {
        $props = new NewProperties;
        $props->geoPoint('location');

        $document = new Document(['location' => (object) ['lat' => 48.8, 'lon' => 2.4]]);

        $this->assertSame($document, (new DocumentProcessor($props->get()))->validateFields($document));
    }

    public function test_stored_bit_vectors_default_to_euclidean_similarity(): void
    {
        $mapping = ['type' => 'dense_vector', 'dims' => 64, 'element_type' => 'bit'];
        $field = DenseVector::fromMapping('v', $mapping);

        $this->assertSame(VectorSimilarity::Euclidean, $field->similarity());
    }

    public function test_date_facet_uses_the_first_format_in_iteration_order(): void
    {
        $field = new DateField('day', [2 => 'yyyy-MM-dd', 4 => 'epoch_millis']);
        $aggs = new Aggs;
        $field->aggregation($aggs, '10');

        $this->assertSame('yyyy-MM-dd', $aggs->toRaw()['day']['stats']['format']);
    }

    public function test_completion_context_names_match_the_mapping(): void
    {
        $plain = (new NewProperties)->completion('suggest');
        $value = ['input' => 'Mars', 'contexts' => ['genre' => 'science']];
        $this->assertFalse($plain->validate('suggest', $value)[0]);

        $field = Text::fromRaw(['suggest' => ['type' => 'completion', 'contexts' => [['name' => 'genre', 'type' => 'category']]]]);
        $this->assertTrue($field->validate('suggest', $value)[0]);
        $this->assertFalse($field->validate('suggest', ['input' => 'Mars', 'contexts' => ['other' => 'science']])[0]);
    }

    public function test_vector_documents_validate_the_whole_mapped_vector(): void
    {
        $props = new NewProperties;
        $props->vector('v', 2);

        $processor = new DocumentProcessor($props->get());
        $doc = new Document(['v' => [0, 1]]);
        $this->assertSame($doc, $processor->validateFields($doc));
        $this->expectException(InvalidArgumentException::class);
        $processor->validateFields(new Document(['v' => [1]]));
    }

    public function test_dense_vector_values_match_their_element_encoding(): void
    {
        $bit = DenseVector::fromMapping('v', ['type' => 'dense_vector', 'dims' => 16, 'element_type' => 'bit']);
        $this->assertFalse($bit->validate('v', [128, 0])[0]);
        $this->assertTrue($bit->validate('v', 'ff00')[0]);
        $this->assertTrue($bit->validate('v', [0, 0])[0]);

        $float = DenseVector::fromMapping('v', ['type' => 'dense_vector', 'dims' => 2]);
        $this->assertFalse($float->validate('v', [0, '1'])[0]);
        $this->assertFalse($float->validate('v', [0, 0])[0]);
        $this->assertTrue($float->validate('v', [0, 1])[0]);
    }

    public function test_numeric_fields_respect_disabled_coercion(): void
    {
        $fields = Properties::create(['n' => ['type' => 'integer', 'coerce' => false]], new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $this->assertFalse($fields['n']->validate('n', '2')[0]);
        $this->assertFalse($fields['n']->validate('n', 2.3)[0]);
        $this->assertTrue($fields['n']->validate('n', 2.0)[0]);
    }

    public function test_integer_ranges_require_a_nonempty_interval(): void
    {
        $range = new Range('stock');
        $this->assertFalse($range->validate('stock', ['gte' => 2, 'lte' => 1])[0]);
        $this->assertFalse($range->validate('stock', ['gt' => 3, 'lt' => 4])[0]);
        $this->assertTrue($range->validate('stock', ['gte' => '3.5', 'lte' => '3.2'])[0]);
    }

    public function test_object_lists_skip_null_records(): void
    {
        $props = new NewProperties;
        $props->nested('items', fn (NewProperties $items): Number => $items->integer('qty'));

        $processor = new DocumentProcessor($props->get());
        $doc = new Document(['items' => [null, ['qty' => 2]]]);
        $this->assertSame($doc, $processor->validateFields($doc));
        $this->expectException(InvalidArgumentException::class);
        $processor->validateFields(new Document(['items' => [null, ['qty' => 'many']]]));
    }

    public function test_geo_points_parse_text_coordinates(): void
    {
        $field = new GeoPoint('location');
        foreach (['12,24', 'POINT (24 12)', 'u4pruydqqvj'] as $point) {
            $this->assertTrue($field->validate('location', $point)[0]);
        }

        $this->assertFalse($field->validate('location', '120,24')[0]);
        $this->assertFalse($field->validate('location', 'POINT (24 120)')[0]);
    }

    public function test_native_scalar_mapping_options_survive_restoration(): void
    {
        $mapping = [
            'count' => ['type' => 'integer', 'coerce' => false, 'store' => true],
            'code' => ['type' => 'keyword', 'ignore_above' => 18, 'doc_values' => false],
            'flag' => ['type' => 'boolean', 'null_value' => true],
        ];
        $fields = Properties::create($mapping, new DefaultAnalyzer, [], Properties::ROOT_NAME);
        foreach ($mapping as $name => $options) {
            $this->assertSame($options, array_intersect_key($fields[$name]->toRaw()[$name], $options));
        }

        $mapping = ['code' => ['type' => 'keyword', 'fields' => [0 => ['type' => 'keyword']]]];
        $fields = Properties::create($mapping, new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $this->assertStringContainsString('"fields":{"0":{"type":"keyword"}}', json_encode($fields->toRaw(), JSON_THROW_ON_ERROR));
    }

    public function test_restored_keyword_keeps_the_stored_normalizer(): void
    {
        $mapping = ['plain' => ['type' => 'keyword'], 'folded' => ['type' => 'keyword', 'normalizer' => 'lowercase']];
        $fields = Properties::create($mapping, new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $this->assertArrayNotHasKey('normalizer', $fields['plain']->toRaw()['plain']);
        $this->assertSame('lowercase', $fields['folded']->toRaw()['folded']['normalizer']);
    }

    public function test_restoring_native_fields_preserves_their_metadata(): void
    {
        $mapping = ['plain' => ['type' => 'boolean'], 'tag' => ['type' => 'integer', 'meta' => ['type' => 'inventory']]];
        $fields = Properties::create($mapping, new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $this->assertArrayNotHasKey('meta', $fields['plain']->toRaw()['plain']);
        $fields['tag']->meta(['unit' => 'box']);
        $this->assertEquals(['type' => 'inventory', 'unit' => 'box'], $fields['tag']->toRaw()['tag']['meta']);
    }

    public function test_scalar_fields_reject_objects_in_document_lists(): void
    {
        $props = new NewProperties;
        $props->integer('count');

        $processor = new DocumentProcessor($props->get());
        $processor->validateFields(new Document(['count' => [[1], null, 2]]));

        $this->expectException(InvalidArgumentException::class);
        $processor->validateFields(new Document(['count' => [['value' => 2]]]));
    }

    public function test_numeric_fields_reject_storage_overflow(): void
    {
        $number = new Number('n');
        $this->assertFalse($number->integer()->validate('n', 2147483648)[0]);
        $this->assertTrue($number->validate('n', -2147483648)[0]);
        $this->assertFalse($number->float()->validate('n', 1e39)[0]);
        $this->assertTrue($number->double()->validate('n', 1e39)[0]);
        $this->assertFalse($number->long()->validate('n', '9.23e18')[0]);
        $this->assertTrue($number->validate('n', '9223372036854775807')[0]);
    }

    public function test_completion_weights_fit_nonnegative_integers(): void
    {
        $field = (new NewProperties)->completion('suggest');
        foreach ([-1, 1.5, null, true, '2147483648', ' 1'] as $weight) {
            $this->assertFalse($field->validate('suggest', ['input' => 'a', 'weight' => $weight])[0]);
        }

        foreach ([0, 2147483647, '+01'] as $weight) {
            $this->assertTrue($field->validate('suggest', ['input' => 'a', 'weight' => $weight])[0]);
        }
    }

    public function test_completion_inputs_reject_reserved_characters(): void
    {
        $field = (new NewProperties)->completion('suggest');
        foreach (["a\0b", "a\x1eb", "a\x1fb", ['input' => ['ok', "a\x1fb"]]] as $value) {
            $this->assertFalse($field->validate('suggest', $value)[0]);
        }
    }

    public function test_geo_points_accept_an_optional_altitude(): void
    {
        $field = new GeoPoint('location');
        foreach ([[1, 2, 3], ['lat' => 2, 'lon' => 1, 'z' => 3]] as $point) {
            $this->assertTrue($field->validate('location', $point)[0]);
        }

        $this->assertFalse($field->validate('location', [1, 2, 'up'])[0]);
    }

    public function test_geo_points_accept_geojson_point_geometry(): void
    {
        $field = new GeoPoint('location');
        $this->assertTrue($field->validate('location', ['type' => 'Point', 'coordinates' => [1, 2]])[0]);
        $this->assertFalse($field->validate('location', ['type' => 'LineString', 'coordinates' => [1, 2]])[0]);
    }

    public function test_numeric_geo_tuples_start_with_a_json_number(): void
    {
        $field = new GeoPoint('location');
        $this->assertFalse($field->validate('location', ['1', 2])[0]);
        $this->assertTrue($field->validate('location', [1, '2'])[0]);
    }

    public function test_range_bounds_accept_php_objects(): void
    {
        $field = new Range('span');
        $this->assertTrue($field->validate('span', (object) ['gte' => 1])[0]);
    }

    public function test_range_bounds_match_their_mapped_type(): void
    {
        $range = new Range('span');
        $this->assertFalse($range->validate('span', ['gte' => 'many'])[0]);
        $this->assertTrue($range->validate('span', ['gte' => '3.5', 'lte' => null])[0]);
        $this->assertFalse($range->ip()->validate('span', ['gte' => 1])[0]);
        $this->assertTrue($range->validate('span', ['gt' => '2001:db8::1'])[0]);
        $this->assertFalse($range->date()->validate('span', ['gte' => 'yesterday'])[0]);
        $this->assertFalse($range->validate('span', ['gt' => 4, 'lt' => 2])[0]);
        $this->assertTrue($range->validate('span', ['gte' => '2024-02-29||+1d'])[0]);
    }

    public function test_price_copies_its_value_to_the_requested_field(): void
    {
        $field = (new Price('price'))->copyTo('all_prices');
        $this->assertSame('all_prices', $field->toRaw()['price']['copy_to'] ?? null);
    }

    public function test_empty_containers_report_no_fields(): void
    {
        foreach ([new Object_('profile'), new Nested('items')] as $container) {
            $this->assertFalse($container->hasFields());
            $props = new NewProperties;
            $props->keyword('sku');
            $this->assertTrue($container->properties($props)->hasFields());
        }
    }

    public function test_object_validates_each_entry_in_a_list_of_objects(): void
    {
        $object = new Object_('items');
        $this->assertTrue($object->validate('items', [[], []])[0]);
        $this->assertFalse($object->validate('items', [['sku' => 'red'], 'blue'])[0]);
    }

    public function test_properties_append_uses_the_field_name(): void
    {
        $properties = new Properties;
        $properties[] = new Number('size');
        $properties[] = new Number('weight');
        $this->assertSame(['size', 'weight'], array_keys($properties->toArray()));
    }

    public function test_changes_to_properties_refresh_the_active_boost(): void
    {
        $blueprint = new NewProperties;
        $blueprint->boost('first');

        $properties = $blueprint->get();
        $properties['second'] = (new NewProperties)->boost('second');
        unset($properties['first']);
        $this->assertSame($properties['second'], $properties->boostField);
        unset($properties['second']);
        $this->assertNull($properties->boostField ?? null);
    }

    public function test_field_inserted_under_nested_object_inherits_its_scope(): void
    {
        $props = new NewProperties;
        $props->nested('items', fn (NewProperties $items): Object_ => $items->object('details'));

        $details = $props->get()->get('items.details')->getProperties();
        $details['sku'] = new Keyword('sku');
        $this->assertSame('items.details.sku', $details['sku']->fullPath());
        $this->assertSame('items', $details['sku']->nestedPath());
    }

    public function test_copy_preserves_deep_descendant_paths(): void
    {
        $props = new NewProperties;
        $props->nested('items', fn (NewProperties $items): Object_ => $items->object('details',
            fn (NewProperties $details): Keyword => $details->keyword('sku')));
        $copy = $props->get()->get('items')->getProperties()->copy();
        $sku = $copy->get('details.sku');
        $this->assertSame('items.details.sku', $sku->fullPath());
        $this->assertSame('items', $sku->nestedPath());
    }

    public function test_geographic_coordinate_arrays_use_longitude_first(): void
    {
        $point = new GeoPoint('location');
        $this->assertTrue($point->validate('location', [120, -30])[0]);
        $this->assertTrue($point->validate('location', [[120, -30], [-100, 45]])[0]);
        $this->assertFalse($point->validate('location', [-30, 120])[0]);
    }

    public function test_ip_ranges_accept_cidr_blocks(): void
    {
        $field = (new Range('subnet'))->ip();
        foreach (['192.0.2.0/24', '2001:db8::/48'] as $block) {
            $this->assertTrue($field->validate('subnet', $block)[0]);
        }

        $this->assertFalse($field->validate('subnet', '192.0.2.0/64')[0]);
    }

    public function test_unset_dense_vector_graph_options_use_engine_defaults(): void
    {
        $field = new DenseVector('features', m: null, efConstruction: 80);
        $this->assertSame(['type' => 'hnsw', 'ef_construction' => 80], $field->toRaw()['features']['index_options']);
    }

    public function test_dense_vector_numeric_metadata_encodes_a_named_map(): void
    {
        $field = new DenseVector('features');
        $field->meta(['0' => 'catalog']);
        $this->assertSame('{"0":"catalog"}', json_encode($field->toRaw()['features']['meta']));
    }

    public function test_converting_a_stored_vector_retains_its_metadata(): void
    {
        $raw = ['features' => ['type' => 'knn_vector', 'dimension' => 16]];
        $props = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $props->get('features')->meta(['source' => 'catalog']);
        $this->assertSame(['source' => 'catalog'], $props->toRaw(new Elasticsearch)['features']['meta'] ?? null);
    }

    public function test_restored_containers_keep_their_indexing_options(): void
    {
        $raw = [
            'payload' => ['type' => 'object', 'enabled' => false],
            'items' => ['type' => 'nested', 'include_in_parent' => true, 'dynamic' => 'strict'],
        ];
        $props = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $mapping = $props->toRaw();
        $this->assertFalse($mapping['payload']['enabled'] ?? true);
        $this->assertTrue($mapping['items']['include_in_parent'] ?? false);
        $this->assertSame('strict', $mapping['items']['dynamic'] ?? null);
    }

    public function test_html_highlight_spanning_a_line_break_preserves_the_tag(): void
    {
        $html = new HTML('body');
        $this->assertSame('<em>one</em><br><em>two</em>', $html->formatHighlight('<em>one<br>two</em>', '<em>', '</em>'));
    }

    public function test_unnormalized_opensearch_dot_product_uses_positive_inner_product_scores(): void
    {
        $driver = new Opensearch;
        $dot = new NestedVector('features', 2, similarity: VectorSimilarity::DotProduct, autoNormalizeVector: false);
        $inner = new NestedVector('features', 2, similarity: VectorSimilarity::MaxInnerProduct);
        $script = static fn (NestedVector $field): string => $driver->nestedVectorField($field)
            ->vectorQueries([1, 0], 10, new BooleanQuery)[0]->toRaw()['nested']['query']['function_score']['script_score']['script']['source'];
        $this->assertSame($script($inner), $script($dot));
    }

    public function test_restored_name_queries_use_the_prefix_subfield_analyzer(): void
    {
        $props = new NewProperties;
        $name = new Name('person', 2, 3);
        $name->withAnalyzer(new DefaultAnalyzer);

        $props->add($name);
        [$mapping, $restored] = $this->restoredProperties($props);
        $prefix = $restored->get('person')->queries('ada')[1]->toRaw()['match_phrase_prefix']['person.person_text'];
        $this->assertSame($mapping['person']['fields']['person_text']['analyzer'], $prefix['analyzer']);
    }

    public function test_opensearch_scores_unnormalized_nested_dot_products_as_inner_products(): void
    {
        $driver = new Opensearch;
        $query = fn (VectorSimilarity $similarity): array => $driver
            ->nestedVectorField(new NestedVector('title', 2, similarity: $similarity, autoNormalizeVector: false))
            ->vectorQueries([-3, 0], 1, new BooleanQuery)[0]->toRaw();

        $this->assertEquals($query(VectorSimilarity::MaxInnerProduct), $query(VectorSimilarity::DotProduct));
    }

    public function test_restored_text_keeps_native_indexing_options(): void
    {
        $mapping = [
            'type' => 'text',
            'index' => false,
            'store' => true,
            'norms' => false,
            'position_increment_gap' => 12,
        ];
        $text = Text::fromRaw(['body' => $mapping]);

        $this->assertSame($mapping, array_intersect_key($text->toRaw()['body'], $mapping));
    }

    public function test_restored_text_prefixes_allow_the_native_default_minimum(): void
    {
        $text = Text::fromRaw(['body' => ['type' => 'text', 'index_prefixes' => ['max_chars' => 7]]]);

        $this->assertSame('body._index_prefix', $text->indexedPrefixName());
        $this->assertSame(['min_chars' => 2, 'max_chars' => 7], $text->toRaw()['body']['index_prefixes']);
    }

    public function test_replacing_a_text_subfield_registers_only_its_current_analyzer(): void
    {
        $text = new Text('body');
        $text->field((new Text('special'))->withNewAnalyzer(
            fn (NewAnalyzer $analyzer): NewAnalyzer => $analyzer->name('old')->tokenizeOnWhitespaces()->lowercase('letter_case'),
        ));
        $text->field((new Text('special'))->withNewAnalyzer(
            fn (NewAnalyzer $analyzer): NewAnalyzer => $analyzer->name('new')->tokenizeOnWhitespaces()->uppercase('letter_case'),
        ));

        $analysis = new Analysis;
        $text->handleCustomAnalyzer($analysis);

        $this->assertSame(['new'], array_keys($analysis->analyzers()));
        $this->assertSame('new', $text->toRaw()['body']['fields']['special']['analyzer']);
    }

    public function test_direct_text_restore_keeps_both_analyzers(): void
    {
        $text = Text::fromRaw(['body' => [
            'type' => 'text',
            'analyzer' => 'english',
            'search_analyzer' => 'whitespace',
        ]]);

        $mapping = $text->toRaw()['body'];
        $this->assertSame('english', $mapping['analyzer'] ?? null);
        $this->assertSame('whitespace', $mapping['search_analyzer'] ?? null);
        $this->assertSame('whitespace', $text->queries('running')[0]->toRaw()['match']['body']['analyzer']);
    }

    public function test_restored_search_as_you_type_queries_its_configured_shingles(): void
    {
        foreach ([2, 4] as $max) {
            $text = Text::fromRaw(['body' => ['type' => 'search_as_you_type', 'max_shingle_size' => $max]]);
            $fields = array_map(fn (int $size): string => 'body._'.$size.'gram', range(2, $max));

            $this->assertSame(['body', ...$fields], $text->queries('running')[0]->toRaw()['multi_match']['fields']);
            $this->assertSame($max, $text->toRaw()['body']['max_shingle_size'] ?? null);
        }
    }

    public function test_restored_text_selects_a_keyword_subfield_regardless_of_mapping_order(): void
    {
        $fields = ['exact' => ['type' => 'keyword'], 'keyword' => ['type' => 'text']];
        foreach ([$fields, array_reverse($fields, preserve_keys: true)] as $fields) {
            $text = Text::fromRaw(['body' => ['type' => 'text', 'fields' => $fields]]);

            $this->assertSame('body.exact', $text->filterableName());
        }
    }

    public function test_external_configured_keyword_remains_filterable_after_two_restores(): void
    {
        $raw = ['body' => [
            'type' => 'text',
            'meta' => ['source' => 'external'],
            'fields' => ['exact' => ['type' => 'keyword', 'ignore_above' => 64]],
        ]];
        $first = Text::fromRaw($raw);
        $second = Text::fromRaw($first->toRaw());

        $this->assertSame('body.exact', $first->filterableName());
        $this->assertSame('body.exact', $second->filterableName());
        $this->assertSame($raw['body']['fields'], $second->toRaw()['body']['fields']);
    }

    public function test_external_text_keeps_user_metadata_named_type(): void
    {
        $raw = ['body' => ['type' => 'text', 'meta' => ['type' => 'description']]];
        $properties = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame(['type' => 'description'], $properties->get('body')->getMeta());
        $this->assertEquals(['type' => 'description'], (array) $properties->toRaw()['body']['meta']);
    }

    public function test_restored_keyword_keeps_the_index_normalizer(): void
    {
        $raw = [
            'code' => ['type' => 'keyword'],
            'name' => ['type' => 'keyword', 'normalizer' => 'folding', 'ignore_above' => 128],
        ];
        $properties = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME)->toRaw();

        $this->assertArrayNotHasKey('normalizer', $properties['code']);
        $this->assertSame('folding', $properties['name']['normalizer']);
        $this->assertSame(128, $properties['name']['ignore_above']);
    }

    public function test_restored_scalar_fields_keep_native_options(): void
    {
        $raw = [
            'quantity' => ['type' => 'integer', 'coerce' => false],
            'active' => ['type' => 'boolean', 'null_value' => false],
            'location' => ['type' => 'geo_point', 'ignore_z_value' => false],
            'price' => ['type' => 'scaled_float', 'scaling_factor' => 10, 'meta' => ['type' => 'price']],
        ];
        $properties = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertInstanceOf(Number::class, $properties->get('quantity'));
        $this->assertInstanceOf(BooleanField::class, $properties->get('active'));
        $this->assertInstanceOf(GeoPoint::class, $properties->get('location'));
        $this->assertInstanceOf(Price::class, $properties->get('price'));
        foreach ($raw as $field => $config) {
            $this->assertEquals($config, array_intersect_key($properties->toRaw()[$field], $config), $field);
        }
    }

    public function test_completion_round_trip_keeps_numeric_metadata_names(): void
    {
        $text = (new Text('suggest'))->completion();
        $text->meta(['0' => 'label']);

        $restored = Text::fromRaw($text->toRaw());

        $this->assertSame('{"0":"label"}', json_encode($restored->toRaw()['suggest']['meta']));
    }

    public function test_legacy_field_path_selects_the_innermost_nested_scope(): void
    {
        $text = (new Text('sku'))->setPath('orders>items>sku');

        $this->assertSame('orders.items.sku', $text->fullPath());
        $this->assertSame('orders.items', $text->nestedPath());
    }

    public function test_restored_name_uses_the_mapped_prefix_analyzer(): void
    {
        $props = new NewProperties;
        $name = new Name('author', 2, 3);
        $name->withNewAnalyzer(fn (NewAnalyzer $analyzer): NewAnalyzer => $analyzer->tokenizeOnWhitespaces());

        $props->type($name);

        [$mapping, $restored] = $this->restoredProperties($props);
        $query = $restored->get('author')->queries('Ada')[1]->toRaw();
        $this->assertSame($mapping['author']['fields']['author_text']['analyzer'], $query['match_phrase_prefix']['author.author_text']['analyzer']);
    }

    public function test_partial_index_prefix_settings_survive_restoration(): void
    {
        $field = Text::fromRaw(['body' => ['type' => 'text', 'index_prefixes' => ['max_chars' => 12]]]);

        $this->assertSame('body._index_prefix', $field->indexedPrefixName());
        $this->assertSame(['min_chars' => 2, 'max_chars' => 12], $field->toRaw()['body']['index_prefixes']);
    }

    public function test_restored_shingle_count_controls_prefix_query_fields(): void
    {
        foreach ([2, 4] as $size) {
            $field = Text::fromRaw(['title' => ['type' => 'search_as_you_type', 'max_shingle_size' => $size]]);
            $fields = ['title'];
            foreach (range(2, $size) as $gram) {
                $fields[] = sprintf('title._%sgram', $gram);
            }

            $this->assertSame($fields, $field->queries('green tea')[0]->toRaw()['multi_match']['fields']);
            $this->assertSame($size, $field->toRaw()['title']['max_shingle_size']);
        }
    }

    public function test_nested_path_uses_the_innermost_boundary(): void
    {
        $field = (new Keyword('sku'))->setPath('orders>items>details.sku');

        $this->assertSame('orders.items', $field->nestedPath());
        $this->assertSame('orders.items.details.sku', $field->fullPath());
    }

    public function test_moving_a_container_updates_child_field_paths(): void
    {
        $children = new NewProperties;
        $children->keyword('sku');

        $object = (new Object_('product', $children))->setFieldPath('orders.product', 'orders');
        $nested = (new Nested('product', $children))->setFieldPath('orders.product', 'orders');

        foreach ([[$object, 'orders'], [$nested, 'orders.product']] as [$container, $scope]) {
            $sku = $container->getProperties()->get('sku');
            $this->assertSame('orders.product.sku', $sku->name());
            $this->assertSame($scope, $sku->nestedPath());
        }
    }

    public function test_replacing_nested_object_properties_keeps_their_scope(): void
    {
        $properties = new NewProperties;
        $properties->nested('items', fn (NewProperties $items): Object_ => $items->object('details'));

        $details = $properties->get()->get('items.details');
        $replacement = new NewProperties;
        $replacement->keyword('sku');

        $details->properties($replacement);

        $field = $details->getProperties()->get('sku');
        $this->assertSame('items.details.sku', $field->fullPath());
        $this->assertSame('items', $field->nestedPath());
    }

    public function test_html_highlight_crossing_an_opening_tag_stays_balanced(): void
    {
        $field = new HTML('body');
        $this->assertSame('<em>green </em><b><em>tea</em></b>', $field->formatHighlight('<em>green <b>tea</em></b>', '<em>', '</em>'));
    }

    public function test_boost_scales_the_normalized_embedding(): void
    {
        $props = new NewProperties;
        $props->number('weight');
        $props->text('title')->semantic('e')->boostedBy('weight');
        $api = $this->createStub(EmbeddingsApi::class);
        $api->method('maxBatchSize')->willReturn(1);
        $api->method('batchEmbed')->willReturn([['vector' => [0.0, 3.0]]]);
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $api]);
        $doc = $processor->populateEmbeddings(new Document(['title' => 'book', 'weight' => 2]));
        $this->assertSame([[0.0, 2.0]], array_values($doc['_embeddings']['title']));
    }

    public function test_restored_plain_keyword_preserves_case(): void
    {
        $props = Properties::create(['sku' => ['type' => 'keyword']], new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $this->assertArrayNotHasKey('normalizer', $props->toRaw()['sku']);
    }

    public function test_nested_analyzer_runs_once_per_materialization(): void
    {
        $count = 0;
        $props = new NewProperties;
        $props->nested('items', function (NewProperties $children) use (&$count): void {
            $children->text('title')->withNewAnalyzer(function (NewAnalyzer $analyzer) use (&$count): void {
                $count++;
                $analyzer->tokenizeOnWhitespaces();
            });
        });
        $props->get();
        $this->assertSame(1, $count);
    }

    public function test_builder_searchable_facet_has_a_terms_field(): void
    {
        $props = new NewProperties;
        $genre = $props->text('genre')->facetSearchable();
        $this->assertTrue($genre->isFacetable());
        $this->assertSame('genre.keyword', $genre->filterableName());
        $this->assertSame('keyword', $props->get()->toRaw()['genre']['fields']['keyword']['type']);
    }

    public function test_builder_mode_switch_drops_text_only_prefix_mapping(): void
    {
        $props = new NewProperties;
        $props->name('suggest')->completion();
        $mapping = $props->get()->toRaw()['suggest'];
        $this->assertArrayNotHasKey('index_prefixes', $mapping);
        $this->assertNull($props->get()->get('suggest')->indexedPrefixName());
    }

    public function test_builder_scaled_float_shortcut_forwards_the_factor(): void
    {
        $props = new NewProperties;
        $props->scaledFloat('mass', 1000);
        $this->assertSame(1000, $props->get()->toRaw()['mass']['scaling_factor']);
    }

    public function test_builder_unsortable_mapping_types_have_no_sort_field(): void
    {
        $props = new NewProperties;
        foreach ([$props->object('details'), $props->nested('items'), $props->vector('features')] as $field) {
            $this->assertNull($field->sortableName(), $field->type());
        }
    }

    public function test_builder_method_macro_keeps_its_receiver(): void
    {
        $field = new Keyword('sku');
        $catalog = new Properties(fields: ['sku' => $field]);
        NewProperties::macro('catalogField', $catalog->get(...));
        try {
            $this->assertSame($field, (new NewProperties)->catalogField('sku'));
        } finally {
            NewProperties::flushMacros();
        }
    }

    public function test_builder_dotted_name_uses_a_local_multifield_name(): void
    {
        $props = new NewProperties;
        $name = $props->name('person.name');
        $mapping = $props->get()->toRaw()['person.name'];
        $this->assertArrayHasKey('name_text', $mapping['fields']);
        $this->assertContains('person.name.name_text', $name->names());
    }

    public function test_builder_vector_preserves_user_metadata(): void
    {
        $props = new NewProperties;
        $props->vector('features')->meta(['source' => 'catalog']);
        $this->assertSame(['source' => 'catalog'], $props->get()->toRaw(new Elasticsearch)['features']['meta'] ?? null);
    }

    public function test_builder_preset_accepts_explicit_analyzer(): void
    {
        $props = new NewProperties;
        $props->title()->withAnalyzer(new Analyzer('exact_title'));
        $this->assertSame('exact_title', $props->get()->toRaw()['title']['analyzer']);
    }

    public function test_builder_reset_to_default_analyzer_updates_queries(): void
    {
        $props = new NewProperties;
        $field = $props->text('body')->unstructuredText(new Analyzer('previous'))->unstructuredText();
        $this->assertSame('default', $field->searchAnalyzer());
    }

    public function test_builder_accepts_plain_text_as_a_custom_subfield(): void
    {
        $props = new NewProperties;
        $props->text('body')->field(new Text('words'));
        $this->assertSame('text', $props->get()->toRaw()['body']['fields']['words']['type']);
    }

    public function test_builder_explicit_keyword_subfields_enable_filter_and_sort(): void
    {
        $props = new NewProperties;
        $field = $props->text('sku')->field(new Keyword('keyword'))->field(new Keyword('sortable'));
        $this->assertSame('sku.keyword', $field->filterableName());
        $this->assertSame('sku.sortable', $field->sortableName());
    }

    public function test_builder_field_names_are_safe_as_analysis_setting_keys(): void
    {
        $props = new NewProperties;
        $props->title('_title');
        $props->keyword('item.code');
        $props->keyword('item%2Ecode');

        $analysis = new Analysis;
        $raw = $props->get($analysis)->toRaw();
        $this->assertFalse(str_starts_with($raw['_title']['analyzer'], '_'));
        $this->assertStringNotContainsString('.', $raw['item.code']['normalizer']);
        $this->assertNotSame($raw['item.code']['normalizer'], $raw['item%2Ecode']['normalizer']);
    }

    public function test_builder_greater_than_in_field_names_preserves_nested_scope(): void
    {
        $props = new NewProperties;
        $props->keyword('a>b');
        $props->nested('x>y', fn (NewProperties $child): Text => $child->title('t>u'));

        $properties = $props->get();
        $this->assertSame(['a>b', 'x>y.t>u'], $properties->fieldNames());
        $this->assertNull($properties->get('a>b')->nestedPath());
        $this->assertSame('x>y', $properties->get('x>y.t>u')->nestedPath());
    }

    public function test_contract_adding_a_field_to_a_cloned_builder_keeps_the_original_schema(): void
    {
        $original = new NewProperties;
        $original->keyword('sku');

        $copy = clone $original;
        $copy->add(new Keyword('vendor'));

        $this->assertSame(['sku', 'vendor'], $copy->get()->fieldNames());
        $this->assertSame(['sku'], $original->get()->fieldNames());
    }

    public function test_cloned_properties_keep_their_field_configuration(): void
    {
        $original = new NewProperties;
        $title = $original->text('title')->description('Original title');
        $copy = clone $original;

        $title->description('Updated title');

        $this->assertSame('Original title', $copy->get()['title']->getDescription());
        $this->assertSame('Updated title', $original->get()['title']->getDescription());
    }

    public function test_failed_child_configuration_keeps_the_existing_schema(): void
    {
        $properties = new NewProperties;
        $properties->object('record', fn (NewProperties $child): Keyword => $child->keyword('sku'));

        $failure = new RuntimeException('Schema source unavailable');
        try {
            $properties->object('record', fn () => throw $failure);
            $this->fail('The configuration should throw.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame($failure, $runtimeException);
        }

        $this->assertSame(['record.sku'], $properties->get()->fieldNames());
    }

    public function test_contract_mappings_accept_the_raw_named_argument(): void
    {
        $mappings = Mappings::create(raw: ['properties' => ['sku' => ['type' => 'keyword']]], analyzers: []);
        $this->assertInstanceOf(Keyword::class, $mappings->properties()->get('sku'));
    }

    public function test_contract_field_containers_accept_named_properties(): void
    {
        $children = new NewProperties;
        $children->keyword('sku');
        foreach ([new Object_('item'), new Nested('item')] as $container) {
            $container->properties(properties: $children);
            $this->assertInstanceOf(Keyword::class, $container->getProperties()->get('sku'));
        }
    }

    public function test_contract_noop_provider_accepts_the_original_field(): void
    {
        $this->assertSame([], (new Noop)->queries(text: 'sku', originalType: new Text('sku')));
    }

    public function test_unicode_boolean_field_name_matches_ignoring_case(): void
    {
        $field = new BooleanField('Straße');

        $this->assertCount(1, $field->queries('STRASSE'));
        $this->assertSame(['ενεργό'], $field->queries(['ΕΝΕΡΓΌ']));
    }

    public function test_keyed_documents_keep_their_embeddings_across_batches(): void
    {
        $props = new NewProperties;
        $props->text('tags')->semantic('e');
        $api = $this->createStub(EmbeddingsApi::class);
        $api->method('maxBatchSize')->willReturn(1);
        $api->method('batchEmbed')->willReturnCallback(static fn (array $batch): array => [[
            'vector' => $batch[0]['text'] === 'alpha' ? [1.0, 0.0] : [0.0, 1.0],
        ]]);
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $api]);

        $result = $processor->populateEmbeddingsBatch([
            'first' => new Document(['tags' => 'alpha']),
            'second' => new Document(['tags' => 'beta']),
        ]);

        $this->assertSame(['first', 'second'], array_keys($result));
        $this->assertSame([[1.0, 0.0]], array_values($result['first']['_embeddings']['tags']));
        $this->assertSame([[0.0, 1.0]], array_values($result['second']['_embeddings']['tags']));
    }

    public function test_completion_mapping_preserves_indexing_options(): void
    {
        $mapping = [
            'type' => 'completion',
            'preserve_separators' => false,
            'preserve_position_increments' => false,
            'max_input_length' => 80,
            'contexts' => [['name' => 'genre', 'type' => 'category', 'path' => 'genre']],
            'meta' => ['type' => 'title', 'label' => 'Title suggestion'],
        ];

        $properties = Properties::create(['suggest' => $mapping], new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame($mapping, $properties->toRaw()['suggest']);
    }

    public function test_completion_mapping_preserves_a_search_only_analyzer(): void
    {
        $mapping = ['type' => 'completion', 'search_analyzer' => 'whitespace'];
        $properties = Properties::create(['suggest' => $mapping], new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame($mapping, $properties->toRaw()['suggest']);
    }

    public function test_source_fields_still_copy_to_completion_after_mapping_restore(): void
    {
        $properties = Properties::create([
            'title' => ['type' => 'text', 'copy_to' => ['suggest']],
            'slug' => ['type' => 'keyword', 'copy_to' => 'suggest'],
            'suggest' => ['type' => 'completion'],
        ], new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame(['suggest'], $properties->toRaw()['title']['copy_to'] ?? null);
        $this->assertSame('suggest', $properties->toRaw()['slug']['copy_to'] ?? null);
    }

    public function test_weighted_completion_inputs_pass_document_validation(): void
    {
        $properties = Properties::create([
            'genre' => ['type' => 'keyword'],
            'suggest' => [
                'type' => 'completion',
                'contexts' => [['name' => 'genre', 'type' => 'category', 'path' => 'genre']],
            ],
        ], new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $processor = new DocumentProcessor($properties);

        foreach ([
            ['input' => ['Star Trek', 'ST'], 'weight' => 8],
            ['Star Trek', ['input' => 'Star Wars', 'weight' => 0]],
            (object) ['input' => 'Star Trek', 'weight' => 8, 'contexts' => ['genre' => ['sci-fi']]],
        ] as $value) {
            $source = ['genre' => 'sci-fi', 'suggest' => $value];

            $this->assertSame($source, $processor->validateFields(new Document($source))->_source);
        }

        $this->expectException(InvalidArgumentException::class);
        $processor->validateFields(new Document(['suggest' => ['input' => [8], 'weight' => 1]]));
    }

    public function test_an_incomplete_batch_does_not_store_a_partial_average(): void
    {
        $props = new NewProperties;
        $props->text('tags')->semantic('e');
        $api = $this->createStub(EmbeddingsApi::class);
        $api->method('maxBatchSize')->willReturn(10);
        $api->method('batchEmbed')->willReturn([['vector' => array_fill(0, 256, 1.0)]]);
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $api]);

        $this->expectException(UnexpectedValueException::class);
        $processor->populateEmbeddings(new Document(['tags' => ['first', 'second']]));
    }

    public function test_dense_vector_round_trip_preserves_byte_storage(): void
    {
        $raw = ['signature' => ['type' => 'dense_vector', 'dims' => 16, 'index' => false, 'element_type' => 'byte']];
        $properties = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame($raw, $properties->toRaw(new Elasticsearch));
    }

    public function test_each_semantic_model_has_its_own_storage_field(): void
    {
        $props = new NewProperties;
        $text = $props->text('description');
        $text->semantic('english');
        $text->semantic('multilingual');

        $raw = (new Mappings(properties: $props->get()))->toRaw(new Elasticsearch);
        $mapping = json_decode(json_encode($raw['properties']), true);

        $this->assertCount(2, $mapping['_embeddings']['properties']['description']['properties']);
    }

    public function test_restored_mapping_keeps_the_embeddings_tree(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic('e');
        $driver = new Elasticsearch;
        $raw = json_decode(json_encode((new Mappings(properties: $props->get()))->toRaw($driver)), true);
        $restored = Mappings::create($raw, [])->toRaw($driver);

        $this->assertSame($raw['properties']['_embeddings'], json_decode(json_encode($restored['properties']->_embeddings), true));
    }

    public function test_restored_semantic_fields_keep_their_vector_definitions(): void
    {
        $props = new NewProperties;
        $props->nested('items', function (NewProperties $items): void {
            $photo = $items->image('0.hero');
            $photo->semantic('clip', 1, 32);
            $photo->semantic('other-clip', 1, 32);
            $photo->semantic('exact', 7, 64)->searchApi('query')->dotProductSimilarity()
                ->boostedBy('priority')->normalizeVector(false);
        });
        $original = new Mappings(properties: $props->get());
        $driver = new Elasticsearch;
        $raw = json_decode(json_encode($original->toRaw($driver), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        $restored = RawIndex::fromRaw('photos', ['mappings' => $raw])->mappings;
        $field = $restored->properties()->get('items.0.hero');

        $this->assertSame('image', $field->embeddingsType());
        $this->assertEquals($original->properties()->get('items.0.hero')->vectorFields(), $field->vectorFields());
        $replayed = json_decode(json_encode($restored->toRaw($driver), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($raw['properties']['_embeddings'], $replayed['properties']['_embeddings']);
        $this->assertSame($raw['_meta'], $replayed['_meta']);
    }

    public function test_restored_combo_fields_still_populate_documents(): void
    {
        $props = new NewProperties;
        $props->nested('items', function (NewProperties $items): void {
            $items->text('title');
            $items->combo('full.text', ['title']);
        });
        $driver = new Elasticsearch;
        $raw = json_decode(json_encode((new Mappings(properties: $props->get()))->toRaw($driver), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        $restored = RawIndex::fromRaw('books', ['mappings' => $raw])->mappings->properties();
        $document = (new DocumentProcessor($restored))->populateComboFields(new Document(['items' => [['title' => 'Dune']]]));

        $this->assertSame('Dune', $document->_source['items'][0]['full.text'] ?? null);
    }

    public function test_direct_vector_mapping_keeps_its_dimensions(): void
    {
        $props = new NewProperties;
        $props->vector('coordinates', 2);

        $this->assertSame($props->get()->toRaw(new Elasticsearch), $props->get()->toRaw());
    }

    public function test_explicit_nested_vector_has_an_unindexed_child(): void
    {
        $props = new NewProperties;
        $props->type(new NestedVector('chunks', 8));

        $mapping = $props->get()->toRaw(new Elasticsearch);
        $this->assertFalse($mapping['chunks']['properties']->vector['index']);
    }

    public function test_exact_query_serializes_components_as_a_vector(): void
    {
        foreach ([new Elasticsearch, new Opensearch] as $driver) {
            $field = $driver->nestedVectorField(new NestedVector('chunks', 2));
            $query = $field->vectorQueries([3 => 0.0, 8 => 1.0], 1, new BooleanQuery)[0]->toRaw();
            $script = $query['nested']['query']['function_score']['script_score']['script'];

            $this->assertSame('[0,1]', json_encode($script['params']->query_vector));
        }
    }

    public function test_driver_conversion_preserves_the_nested_vector_path(): void
    {
        $vector = (new BaseVector('embedding', dims: 2))->setPath('items>details.embedding');
        $nested = (new NestedVector('chunks', dims: 2))->setPath('items>details.chunks');

        foreach ([new Elasticsearch, new Opensearch] as $driver) {
            foreach ([$driver->vectorField($vector), $driver->nestedVectorField($nested)] as $field) {
                $this->assertSame('items', $field->nestedPath());
                $this->assertSame('items.details.'.$field->name, $field->fullPath());
            }
        }
    }

    public function test_explicit_knn_vector_uses_the_selected_engine(): void
    {
        $props = new NewProperties;
        $props->type(new KnnVector('embedding', dims: 2, similarity: VectorSimilarity::Euclidean));

        $elastic = $props->get()->toRaw(new Elasticsearch)['embedding'];

        $this->assertSame('dense_vector', $elastic['type']);
        $this->assertSame(2, $elastic['dims']);
        $this->assertSame('l2_norm', $elastic['similarity']);
        $this->assertSame('knn_vector', $props->get()->toRaw(new Opensearch)['embedding']['type']);
    }

    public function test_flat_object_uses_each_engines_mapping(): void
    {
        $props = new NewProperties;
        $props->type(new FlatObject('metadata'));

        $this->assertSame(['type' => 'flat_object'], $props->get()->toRaw(new Opensearch)['metadata']);
        $elastic = $props->get()->toRaw(new Elasticsearch);
        $this->assertSame('flattened', $elastic['metadata']['type']);
        $restored = Properties::create($elastic, new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $this->assertInstanceOf(FlatObject::class, $restored->get('metadata'));
    }

    public function test_restored_opensearch_mapping_preserves_native_vector_options(): void
    {
        $raw = ['embedding' => [
            'type' => 'knn_vector',
            'dimension' => 2,
            'data_type' => 'BYTE',
            'method' => ['engine' => 'lucene', 'name' => 'hnsw', 'space_type' => 'l2', 'parameters' => []],
        ]];
        $properties = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $raw['embedding']['method']['parameters'] = (object) [];
        $this->assertSame(json_encode($raw), json_encode($properties->toRaw(new Opensearch)));
    }

    public function test_opensearch_reads_the_vector_space_from_the_field(): void
    {
        $cases = [
            [[], VectorSimilarity::Euclidean],
            [['space_type' => 'innerproduct'], VectorSimilarity::DotProduct],
        ];

        foreach ($cases as [$options, $similarity]) {
            $raw = ['v' => ['type' => 'knn_vector', 'dimension' => 2, ...$options]];
            $properties = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME);
            $this->assertSame($similarity, $properties->get('v')->similarity());
        }
    }

    public function test_opensearch_omits_unset_hnsw_parameters(): void
    {
        $cases = [
            [null, 80, ['ef_construction' => 80]],
            [16, null, ['m' => 16]],
            [null, null, []],
        ];

        foreach ($cases as [$m, $ef, $expected]) {
            $field = new BaseVector('vector', m: $m, efConstruction: $ef);
            $method = (new Opensearch)->vectorField($field)->toRaw()['vector']['method'];
            $this->assertSame($expected, $method['parameters'] ?? []);
        }
    }

    public function test_field_type_audit_rejects_malformed_geographic_coordinates(): void
    {
        $props = new NewProperties;
        $field = $props->geoPoint('location');

        $this->assertTrue($field->validate('location', [['lat' => 48.8, 'lon' => 2.4]])[0]);
        $this->assertFalse($field->validate('location', ['lat' => 'north', 'lon' => 2.4])[0]);
        $this->assertFalse($field->validate('location', [['lat' => 92, 'lon' => 2.4]])[0]);
    }

    public function test_field_type_audit_returns_validation_errors_for_dates_with_null_bytes(): void
    {
        $props = new NewProperties;
        $date = $props->date('release_date');
        $dateTime = $props->datetime('released_at');

        $this->assertFalse($date->validate('release_date', "2024-01-01\0")[0]);
        $this->assertFalse($dateTime->validate('released_at', "2024-01-01T10:11:12\0")[0]);
    }

    public function test_field_type_audit_validates_zone_free_timestamps_in_utc(): void
    {
        $props = new NewProperties;
        $field = $props->datetime('created_at');
        $timezone = date_default_timezone_get();

        try {
            date_default_timezone_set('America/New_York');
            $this->assertTrue($field->validate('created_at', '2024-03-10T02:30:00')[0]);
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    public function test_field_type_audit_validates_datetime_utc_offsets(): void
    {
        $props = new NewProperties;
        $field = $props->datetime('created_at');

        $this->assertTrue($field->validate('created_at', '2024-01-01T10:00:00-00:00')[0]);
        $this->assertFalse($field->validate('created_at', '2024-01-01T10:00:00+25:00')[0]);
    }

    public function test_field_type_audit_rejects_unknown_range_bounds(): void
    {
        $props = new NewProperties;
        $field = $props->range('age');

        $this->assertTrue($field->validate('age', ['gte' => 18, 'lte' => 65])[0]);
        $this->assertFalse($field->validate('age', ['gte' => 18, 'typo' => 65])[0]);
    }

    public function test_field_type_audit_rejects_nonfinite_numbers(): void
    {
        $props = new NewProperties;
        $number = $props->double('rating');
        $price = $props->price();

        $this->assertTrue($number->validate('rating', '1.25')[0]);
        $this->assertFalse($number->validate('rating', '1e999')[0]);
        $this->assertFalse($price->validate('price', INF)[0]);
    }

    /**
     * @test
     */
    public function category_mapping_includes_keyword_and_sortable(): void
    {
        $props = new NewProperties;
        $props->category('genre');

        $fields = $props->get()->toRaw()['genre']['fields'];

        $this->assertArrayHasKey('keyword', $fields);
        $this->assertArrayHasKey('sortable', $fields);
    }

    /**
     * @test
     */
    public function object_validate_accepts_one_flat_object(): void
    {
        $director = new Object_('director');

        $this->assertSame([true, ''], $director->validate('director', [
            'name' => 'Nolan',
            'birth_year' => 1970,
        ]));
        $this->assertSame([true, ''], $director->validate('director', []));
        $this->assertFalse($director->validate('director', [1])[0]);
    }

    /**
     * @test
     */
    public function an_object_child_named_zero_stays_valid(): void
    {
        $props = new NewProperties;
        $director = $props->object('director', function (NewProperties $props): void {
            $props->keyword('0');
        });

        $this->assertSame([true, ''], $director->validate('director', ['0' => 'Nolan']));
    }

    /**
     * @test
     */
    public function a_nested_item_field_named_zero_stays_valid(): void
    {
        $props = new NewProperties;
        $cast = $props->nested('cast', function (NewProperties $props): void {
            $props->keyword('0');
        });

        $this->assertSame([true, ''], $cast->validate('cast', [['0' => 'Neo']]));
    }

    /**
     * @test
     */
    public function id_sortable_name_uses_the_field_path(): void
    {
        $this->assertSame('user_id.sortable', (new Id('user_id'))->sortableName());

        $props = new NewProperties;
        $props->object('author', function (NewProperties $props): void {
            $props->id('id');
        });

        $this->assertSame('author.id.sortable', $props->get()->get('author.id')->sortableName());
    }

    /**
     * @test
     */
    public function nested_object_child_keeps_nested_path(): void
    {
        $props = new NewProperties;
        $props->nested('items', function (NewProperties $items): void {
            $items->keyword('name');
            $items->object('details', function (NewProperties $details): void {
                $details->number('price')->integer();
            });
        });

        $built = $props->get();

        $this->assertSame('items', $built->get('items.name')->nestedPath());
        $this->assertSame('items', $built->get('items.details')->nestedPath());
        $this->assertSame('items', $built->get('items.details.price')->nestedPath());
    }

    /**
     * @test
     */
    public function accuracy_7_nested_vector_omits_hnsw_index(): void
    {
        $field = (new NewSemanticField('content'))
            ->api('embeddings')
            ->accuracy(7, 256)
            ->euclideanSimilarity()
            ->make();

        $elasticsearch = json_decode(json_encode((new Elasticsearch)->nestedVectorField($field)->toRaw()), true);
        $opensearch = json_decode(json_encode((new Opensearch)->nestedVectorField($field)->toRaw()), true);

        $elasticsearchVector = $elasticsearch[$field->name]['properties']['vector'];
        $opensearchVector = $opensearch[$field->name]['properties']['vector'];

        $this->assertFalse($elasticsearchVector['index']);
        $this->assertArrayNotHasKey('index_options', $elasticsearchVector);
        $this->assertArrayNotHasKey('method', $opensearchVector);
        $this->assertStringNotContainsString('ef_construction', json_encode([$elasticsearch, $opensearch]));
        $this->assertStringNotContainsString('"m"', json_encode([$elasticsearch, $opensearch]));
    }

    /**
     * @test
     */
    public function boolean_queries_accept_an_array(): void
    {
        $queries = (new BooleanField('active'))->queries(['active', 'ACTIVE']);

        $this->assertSame(['active', 'active'], $queries);
    }

    /**
     * @test
     */
    public function an_array_of_query_strings_builds_each_query(): void
    {
        $props = new NewProperties;
        $title = $props->text('title');
        $matches = array_map(
            static fn (object $query): string => $query->toRaw()['match']['title']['query'],
            $title->queries(['hello', 'world']),
        );

        $this->assertSame(['hello', 'world'], $matches);

        $sku = $props->keyword('sku')->queries(['ABC']);

        $this->assertSame('ABC', $sku[0]->toRaw()['term']['sku']['value']);
        $this->assertSame('ABC', $sku[1]->toRaw()['prefix']['sku']['value']);

        $code = (new CaseSensitiveKeyword('code'))->queries(['ABC']);

        $this->assertSame('ABC', $code[0]->toRaw()['term']['code']['value']);
        $this->assertSame('ABC', $code[1]->toRaw()['prefix']['code']['value']);

        $email = $props->email('email')->queries(['a@b.co']);

        $this->assertSame('a@b.co', $email[0]->toRaw()['match']['email']['query']);

        $callback = $props->text('contact')->withQueries(fn (string $queryString): array => [new Term('contact', $queryString)]);
        $terms = array_map(
            static fn (object $query): string => $query->toRaw()['term']['contact']['value'],
            $callback->queryStringQueries(['left', 'right']),
        );

        $this->assertSame(['left', 'right'], $terms);
    }

    /**
     * @test
     */
    public function nested_validate_accepts_an_empty_list(): void
    {
        $props = new NewProperties;
        $props->nested('cast', function (NewProperties $props): void {
            $props->keyword('actor');
        });

        $cast = $props->get()->get('cast');

        $this->assertSame([true, ''], $cast->validate('cast', []));
        $this->assertSame([true, ''], $cast->validate('cast', ['actor' => 'Keanu']));
    }

    /**
     * @test
     */
    public function nested_vector_to_raw_returns_an_array(): void
    {
        $field = (new NewSemanticField('content'))
            ->api('embeddings')
            ->accuracy(7, 256)
            ->make();

        $raw = $field->toRaw();

        $this->assertIsArray($raw);
        $this->assertIsString(((array) $raw[$field->name]['properties'])['vector']['type']);
    }

    /**
     * @test
     */
    public function parent_named_zero_stays_in_the_child_path(): void
    {
        $props = new NewProperties;
        $props->object('0', function (NewProperties $props): void {
            $props->keyword('direct');
            $props->object('mid', function (NewProperties $props): void {
                $props->keyword('child');
            });
        });

        $built = $props->get();

        $this->assertSame('0.direct', $built->get('0.direct')->fullPath());
        $this->assertSame('0.mid.child', $built->get('0.mid.child')->fullPath());
        $this->assertSame('0.mid.child', $built->get('0.mid.child')->name());
        $this->assertContains('0.mid.child', $built->fieldNames(true));

        $textProps = new NewProperties;
        $textProps->text('0')->semantic(api: 'embeddings', accuracy: 1, dimensions: 128);

        foreach ($textProps->get()->get('0')->vectorFields() as $vector) {
            $this->assertStringStartsWith('0.', $vector->fullPath());
        }
    }

    /**
     * @test
     */
    public function created_nested_field_keeps_its_nested_path(): void
    {
        $props = new NewProperties;
        $props->nested('cast', function (NewProperties $props): void {
            $props->keyword('actor');
        });
        $props->nested('orders', function (NewProperties $orders): void {
            $orders->nested('items', function (NewProperties $items): void {
                $items->keyword('sku');
            });
        });

        $raw = json_decode(json_encode($props->get()->toRaw()), true);
        $restored = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame('cast', $restored->get('cast.actor')->nestedPath());
        $this->assertSame('orders.items', $restored->get('orders.items.sku')->nestedPath());

        $sort = (new SortParser($restored, false))->parse('cast.actor:asc');

        $this->assertSame([
            ['cast.actor' => ['nested' => ['path' => 'cast'], 'order' => 'asc']],
        ], $sort);
    }

    /**
     * @test
     */
    public function an_added_container_keeps_its_descendant_path(): void
    {
        $addressProps = new NewProperties;
        $addressProps->keyword('city');

        $address = new Object_('address', $addressProps);

        $person = new NewProperties;
        $person->object('person', function (NewProperties $props) use ($address): void {
            $props->add($address);
        });

        $city = $person->get()->get('person.address.city');
        $term = $city->queries('Berlin')[0]->toRaw()['term'];

        $this->assertSame('person.address', $person->get()->get('person.address')->fullPath());
        $this->assertSame('person.address.city', $city->fullPath());
        $this->assertSame('Berlin', $term['person.address.city']['value']);

        $skuProps = new NewProperties;
        $skuProps->keyword('sku');

        $items = new Nested('items', $skuProps);

        $orders = new NewProperties;
        $orders->nested('orders', function (NewProperties $props) use ($items): void {
            $props->add($items);
        });

        $sku = $orders->get()->get('orders.items.sku');
        $skuTerm = $sku->queries('A1')[0]->toRaw()['term'];

        $this->assertSame('orders.items.sku', $sku->fullPath());
        $this->assertSame('orders.items', $sku->nestedPath());
        $this->assertSame('A1', $skuTerm['orders.items.sku']['value']);
    }

    /**
     * @test
     */
    public function object_and_nested_numbers_sort_on_the_full_field_path(): void
    {
        $props = new NewProperties;
        $props->object('director', function (NewProperties $props): void {
            $props->number('birth_year')->integer();
        });
        $props->nested('cast', function (NewProperties $props): void {
            $props->number('screen_time')->integer();
        });

        $built = $props->get();
        $parser = new SortParser($built, false);

        $this->assertSame('director.birth_year', $built->get('director.birth_year')->sortableName());
        $this->assertSame('cast.screen_time', $built->get('cast.screen_time')->sortableName());
        $this->assertSame([['director.birth_year' => 'asc']], $parser->parse('director.birth_year:asc'));
        $this->assertSame([
            ['cast.screen_time' => ['nested' => ['path' => 'cast'], 'order' => 'desc']],
        ], $parser->parse('cast.screen_time:desc'));
    }

    /**
     * @test
     */
    public function create_keeps_scaled_float_factor_and_date_format(): void
    {
        $props = new NewProperties;
        $props->number('amount')->scaledFloat(1000);
        $props->date('created_at')->format('MM/dd/yyyy');

        $raw = json_decode(json_encode($props->get()->toRaw()), true);
        $restored = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame(1000, $restored->get('amount')->toRaw()['amount']['scaling_factor']);
        $this->assertSame(
            'strict_date_optional_time_nanos||MM/dd/yyyy',
            $restored->get('created_at')->toRaw()['created_at']['format']
        );
    }

    /**
     * @test
     */
    public function restored_text_keeps_keyword_and_sortable(): void
    {
        $props = new NewProperties;
        $props->text('plain')->keyword()->makeSortable();
        $props->text('prefixed')->indexPrefixes(2, 8);

        $raw = json_decode(json_encode($props->get()->toRaw()), true);
        $restored = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $plain = $restored->get('plain');
        $prefixed = $restored->get('prefixed');

        $parser = new SortParser($restored, false);

        $this->assertTrue($plain->isSortable());
        $this->assertArrayHasKey('keyword', $plain->toRaw()['plain']['fields']);
        $this->assertArrayHasKey('sortable', $plain->toRaw()['plain']['fields']);
        $this->assertSame([['plain.sortable' => 'asc']], $parser->parse('plain:asc'));
        $this->assertSame([], $parser->errors());
        $this->assertSame(
            ['min_chars' => 2, 'max_chars' => 8],
            $prefixed->toRaw()['prefixed']['index_prefixes']
        );
    }

    /**
     * @test
     */
    public function restored_category_keeps_its_class(): void
    {
        $props = new NewProperties;
        $props->category('genre');
        $props->price('fee');

        $restored = Properties::create(
            json_decode(json_encode($props->get()->toRaw()), true),
            new DefaultAnalyzer,
            [],
            Properties::ROOT_NAME
        );
        $genre = $restored->get('genre');
        $fee = $restored->get('fee');

        $this->assertInstanceOf(Category::class, $genre);
        $this->assertTrue($genre->isSortable());
        $this->assertArrayHasKey('keyword', $genre->toRaw()['genre']['fields']);
        $this->assertSame('category', $genre->toRaw()['genre']['meta']['type']);
        $this->assertInstanceOf(Price::class, $fee);
        $this->assertSame('scaled_float', $fee->type());
    }

    /**
     * @test
     */
    public function name_fields_keep_their_own_gram_ranges(): void
    {
        $analysis = new Analysis;
        $built = (new NewProperties)
            ->type(new Name('first', 2, 3))
            ->type(new Name('last', 4, 6))
            ->get($analysis);

        $raw = $built->toRaw();
        $settings = $analysis->toRaw();
        $firstAnalyzer = $raw['first']['analyzer'];
        $lastAnalyzer = $raw['last']['analyzer'];

        $this->assertNotSame($firstAnalyzer, $lastAnalyzer);
        $this->assertSame(2, $settings['filter'][$settings['analyzer'][$firstAnalyzer]['filter'][0]]['min_gram']);
        $this->assertSame(3, $settings['filter'][$settings['analyzer'][$firstAnalyzer]['filter'][0]]['max_gram']);
        $this->assertSame(4, $settings['filter'][$settings['analyzer'][$lastAnalyzer]['filter'][0]]['min_gram']);
        $this->assertSame(6, $settings['filter'][$settings['analyzer'][$lastAnalyzer]['filter'][0]]['max_gram']);
    }

    /**
     * @test
     */
    public function price_type_is_scaled_float(): void
    {
        $this->assertSame('scaled_float', (new Price('fee'))->type());
    }

    /**
     * @test
     */
    public function nested_validate_rejects_a_value_that_is_not_a_list_of_objects(): void
    {
        $props = new NewProperties;
        $props->nested('cast', function (NewProperties $props): void {
            $props->keyword('actor');
        });

        $cast = $props->get()->get('cast');

        $this->assertSame([true, ''], $cast->validate('cast', ['actor' => ['Keanu']]));
        $this->assertFalse($cast->validate('cast', [['actor' => 'A'], 'x'])[0]);
        $this->assertSame([true, ''], $cast->validate('cast', [['actor' => 'A']]));
    }

    /**
     * @test
     */
    public function a_field_query_uses_its_search_analyzer(): void
    {
        $props = new NewProperties;
        $props->html('content');
        $props->path('file_path');
        $props->longText('synopsis')->withNewAnalyzer(function (NewAnalyzer $analyzer): void {
            $analyzer->tokenizeOnWhitespaces()->lowercase();
        });
        $props->shortText('blurb')->withNewAnalyzer(function (NewAnalyzer $analyzer): void {
            $analyzer->tokenizeOnWhitespaces()->lowercase();
        });
        $props->searchAsYouType('lookup')->withNewAnalyzer(function (NewAnalyzer $analyzer): void {
            $analyzer->tokenizeOnWhitespaces()->lowercase();
        });
        $built = $props->get(new Analysis);

        $content = $built->get('content')->queries('hello')[0]->toRaw();
        $path = $built->get('file_path')->queries('hello')[1]->toRaw();
        $synopsis = $built->get('synopsis')->queries('hello')[0]->toRaw();
        $blurb = $built->get('blurb')->queries('hello')[0]->toRaw();
        $lookup = $built->get('lookup')->queries('hello')[0]->toRaw();

        $this->assertSame('content_field_analyzer', $content['match']['content']['analyzer']);
        $this->assertSame('file_path_field_analyzer', $path['match']['file_path']['analyzer']);
        $this->assertSame('synopsis_field_analyzer', $synopsis['match']['synopsis']['analyzer']);
        $this->assertSame('blurb_field_analyzer', $blurb['match']['blurb']['analyzer']);
        $this->assertArrayNotHasKey('analyzer', $lookup['multi_match']);
        $this->assertSame('lookup_field_analyzer', $built->get('lookup')->toRaw()['lookup']['analyzer']);
    }

    /**
     * @test
     */
    public function names_use_the_full_field_path(): void
    {
        $props = new NewProperties;
        $props->object('book', function (NewProperties $props): void {
            $props->text('title');
            $props->name('author');
        });
        $built = $props->get();

        $this->assertSame(['book.title'], $built->get('book.title')->names());
        $this->assertSame(
            ['book.author', 'book.author.author_text'],
            $built->get('book.author')->names(),
        );
    }

    /**
     * @test
     */
    public function search_synonyms_false_keeps_the_default_analyzer(): void
    {
        $off = (new Text('title'))->unstructuredText()->searchSynonyms(false);
        $on = (new Text('title'))->unstructuredText()->searchSynonyms(true);

        $this->assertSame('default', $off->searchAnalyzer());
        $this->assertSame('default_with_synonyms', $on->searchAnalyzer());
        $this->assertSame('default', $off->queries('ipod')[0]->toRaw()['match']['title']['analyzer']);
    }

    /**
     * @test
     */
    public function an_object_mapping_keeps_its_properties(): void
    {
        $restored = Properties::create([
            'director' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'keyword'],
                    'birth_year' => ['type' => 'integer'],
                ],
            ],
        ], new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertInstanceOf(Keyword::class, $restored->get('director.name'));
        $this->assertSame(['director.name', 'director.birth_year'], $restored->fieldNames());
    }

    /**
     * @test
     */
    public function a_date_time_mapping_restores_a_datetime(): void
    {
        $restored = Properties::create([
            'created_at' => [
                'type' => 'date',
                'meta' => ['type' => 'date_time'],
                'format' => 'strict_date_optional_time_nanos',
            ],
        ], new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $field = $restored->get('created_at');

        $this->assertInstanceOf(DateTimeField::class, $field);
        $this->assertSame([true, ''], $field->validate('created_at', '2023-04-07T12:38:29.000000Z'));
    }

    /**
     * @test
     */
    public function a_datetime_with_milliseconds_stays_valid(): void
    {
        $props = new NewProperties;
        $props->datetime('created_at');

        $field = $props->get()->get('created_at');
        $input = '2023-04-07T12:38:29.000Z';

        $this->assertSame([true, ''], $field->validate('created_at', $input));
        $this->assertSame([true, ''], $field->validate('created_at', '2023-04-07T12:38:29.123456789+00:00'));
        $this->assertFalse($field->validate('created_at', '2023-02-31T12:38:29.000Z')[0]);

        $processor = new DocumentProcessor($props->get());
        $document = $processor->formatDateTimeFields(new Document(['created_at' => $input]));
        $processor->validateFields($document);

        $this->assertSame($input, $document->_source['created_at']);
    }

    /**
     * @test
     */
    public function a_case_sensitive_keyword_mapping_has_no_normalizer(): void
    {
        $restored = Properties::create([
            'promo_code' => [
                'type' => 'keyword',
                'meta' => ['type' => 'case_sensitive_keyword'],
            ],
        ], new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame(
            ['promo_code' => ['type' => 'keyword', 'meta' => ['type' => 'case_sensitive_keyword']]],
            json_decode(json_encode($restored->get('promo_code')->toRaw()), true),
        );
    }

    /**
     * @test
     */
    public function a_name_mapping_restores_name_queries(): void
    {
        $restored = Properties::create([
            'director' => [
                'type' => 'text',
                'meta' => ['type' => 'name'],
            ],
        ], new DefaultAnalyzer, [], Properties::ROOT_NAME);
        $field = $restored->get('director');

        $this->assertInstanceOf(Name::class, $field);
        // The restored mapping has no `director_text` subfield, so no prefix clauses target it.
        $this->assertSame(
            ['Match_'],
            array_map(static fn (object $query): string => (new ReflectionClass($query))->getShortName(), $field->queries('Nolan')),
        );
    }

    /**
     * @test
     */
    public function an_image_field_keeps_cosine_similarity(): void
    {
        $made = (new NewSemanticField('photo'))
            ->fieldType('image')
            ->accuracy(3, 256)
            ->cosineSimilarity()
            ->make();

        $this->assertSame('cosine', $made->similarity()->value);
    }

    /**
     * @test
     */
    public function an_accuracy_7_field_keeps_a_null_api(): void
    {
        $field = (new NewSemanticField('content'))
            ->accuracy(7, 256)
            ->make();

        $this->assertSame('exact_dims256_cosine_script', $field->name);
        $this->assertNull($field->apiName);
    }

    /**
     * @test
     */
    public function a_date_property_assertion_expects_date(): void
    {
        $assert = new Assert('orders', [
            'mappings' => [
                'properties' => [
                    'created_at' => ['type' => 'keyword'],
                ],
            ],
        ]);

        try {
            $assert->assertPropertyIsDate('created_at');
            $this->fail('a keyword field is not a date');
        } catch (ExpectationFailedException $expectationFailedException) {
            $comparison = $expectationFailedException->getComparisonFailure();
            $this->assertNotNull($comparison);
            $this->assertSame('date', $comparison->getExpected());
            $this->assertSame('keyword', $comparison->getActual());
        }
    }

    /**
     * @test
     */
    public function embeddings_property_reads_the_embeddings_object(): void
    {
        $assert = new Assert('articles', [
            'mappings' => [
                'properties' => [
                    '_embeddings' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
        ]);

        $assert->assertEmbeddingsPropertyEquals('title.type', 'object');
    }

    /**
     * @test
     */
    public function a_nested_semantic_field_is_a_nested_embeddings_object(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->nested('comments', function (NewProperties $props): void {
            $props->text('text')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
            $props->object('user', function (NewProperties $props): void {
                $name = $props->text('name');
                $name->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
                $name->semantic(accuracy: 7, dimensions: 384, api: 'test-embeddings');
            });
        });

        $raw = (new Mappings(properties: $props->get()))->toRaw(new Elasticsearch);
        $properties = json_decode(json_encode($raw['properties']), true)['_embeddings']['properties'];

        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('comments', $properties);
        $this->assertArrayNotHasKey('comments.text', $properties);
        $this->assertArrayNotHasKey('comments.user.name', $properties);
        $this->assertArrayHasKey(
            'm15_efc73_dims384_cosine_concat',
            $properties['comments']['properties']['text']['properties'],
        );
        $this->assertArrayHasKey(
            'm15_efc73_dims384_cosine_concat',
            $properties['comments']['properties']['user']['properties']['name']['properties'],
        );
        $this->assertArrayHasKey(
            'exact_dims384_cosine_script',
            $properties['comments']['properties']['user']['properties']['name']['properties'],
        );
    }

    /**
     * @test
     */
    public function a_static_macro_is_called(): void
    {
        NewProperties::flushMacros();

        NewProperties::macro('ping', static fn (): string => 'pong');

        try {
            $this->assertSame('pong', (new NewProperties)->ping());

            NewProperties::macro('sku', fn (string $name) => $this->keyword($name));

            $this->assertSame('code', (new NewProperties)->sku('code')->name());
        } finally {
            NewProperties::flushMacros();
        }
    }

    /**
     * @test
     */
    public function a_keyword_subfield_named_zero_stays_in_the_mapping(): void
    {
        $field = Text::fromRaw(json_decode(
            '{"title":{"type":"text","fields":{"0":{"type":"keyword"}}}}',
            true
        ));

        $this->assertStringContainsString('"fields":{"0":', json_encode($field->toRaw()));
        $this->assertSame('title.0', $field->keywordName());

        $named = (new Text('title'))->unstructuredText()->keyword();
        $this->assertStringContainsString('"keyword":{"type":"keyword"}', json_encode($named->toRaw()));
    }

    /**
     * @test
     */
    public function an_id_and_a_searchable_number_round_trip(): void
    {
        $props = new NewProperties;
        $props->id('user_id');
        $props->searchableNumber('phone');
        [$raw, $restored] = $this->restoredProperties($props);

        $id = $restored->get('user_id');
        $phone = $restored->get('phone');

        $this->assertInstanceOf(Id::class, $id);
        $this->assertSame([true, ''], $id->validate('user_id', 7));
        $this->assertSame($raw['user_id'], json_decode(json_encode($id->toRaw()), true)['user_id']);

        $this->assertInstanceOf(SearchableNumber::class, $phone);
        $this->assertSame(
            ['Match_', 'MatchPhrasePrefix'],
            array_map(static fn (object $query): string => (new ReflectionClass($query))->getShortName(), $phone->queries('4412')),
        );
        $this->assertSame($raw['phone'], json_decode(json_encode($phone->toRaw()), true)['phone']);
    }

    /**
     * @test
     */
    public function a_name_mapping_keeps_its_subfields(): void
    {
        $props = new NewProperties;
        $props->name('director');
        [$raw, $restored] = $this->restoredProperties($props);
        $director = $restored->get('director');

        $this->assertTrue($director->isSortable());
        $this->assertSame($raw['director'], json_decode(json_encode($director->toRaw()), true)['director']);

        $parser = new SortParser($restored, false);

        $this->assertSame([['director.sortable' => 'asc']], $parser->parse('director:asc'));
        $this->assertSame([], $parser->errors());
    }

    /**
     * @test
     */
    public function a_completion_mapping_has_no_analyzer(): void
    {
        $props = new NewProperties;
        $props->text('suggest')->completion();
        [$raw, $restored] = $this->restoredProperties($props);

        $this->assertSame(['type' => 'completion'], $raw['suggest']);
        $this->assertSame($raw['suggest'], json_decode(json_encode($restored->get('suggest')->toRaw()), true)['suggest']);
    }

    /**
     * @test
     */
    public function a_combo_field_has_a_type(): void
    {
        $combo = new Combo('full', ['title']);

        $this->assertSame('combo', $combo->type());
        $this->assertSame([], $combo->toRaw());

        $props = new NewProperties;
        $props->text('title');
        $props->combo('full', ['title']);
        $props->completion('suggest');

        $this->assertSame(['suggest'], $props->get()->completionFields()->keys());
    }

    /**
     * @test
     */
    public function a_custom_analyzer_keeps_the_subfields(): void
    {
        $customize = function (NewAnalyzer $analyzer): void {
            $analyzer->tokenizeOnWhitespaces()->lowercase();
        };

        $props = new NewProperties;
        $props->name('director')->withNewAnalyzer($customize);
        $props->title('title')->withNewAnalyzer($customize);
        $props->email('contact')->withNewAnalyzer($customize);
        $props->address('address')->withNewAnalyzer($customize);
        $props->searchableNumber('phone')->withNewAnalyzer($customize);

        $built = $props->get(new Analysis);

        $director = json_decode(json_encode($built->get('director')->toRaw()), true)['director'];

        $this->assertArrayHasKey('director_text', $director['fields']);
        $this->assertSame('keyword', $director['fields']['sortable']['type']);
        $this->assertSame('keyword', $director['fields']['keyword']['type']);

        foreach (['title', 'contact', 'address', 'phone'] as $name) {
            $fields = json_decode(json_encode($built->get($name)->toRaw()), true)[$name]['fields'];

            $this->assertSame('keyword', $fields['sortable']['type'], $name);
            $this->assertSame('keyword', $fields['keyword']['type'], $name);
        }
    }

    /** @test */
    public function mapping_roundtrip_preserves_an_ip_field_and_its_options(): void
    {
        $props = new NewProperties;
        $host = new Field('host_ip', 'ip', ['ignore_malformed' => true]);
        $host->meta(['source' => 'gateway']);

        $props->add($host);

        [$raw, $restored] = $this->restoredProperties($props);

        $this->assertInstanceOf(Field::class, $restored->get('host_ip'));
        $this->assertSame(['source' => 'gateway'], $restored->get('host_ip')->getMeta());
        $this->assertSame($raw, $restored->toRaw());
    }

    /** @test */
    public function mapping_roundtrip_restores_the_named_boost_field(): void
    {
        $props = new NewProperties;
        $boost = $props->boost('popularity');
        $props->number('price')->float();

        [, $restored] = $this->restoredProperties($props);

        $this->assertSame($restored->get('popularity'), $restored->boostField ?? null);
        $this->assertSame($boost->scriptScoreSource(), $restored->boostField->scriptScoreSource());
        $this->assertSame(Number::class, $restored->get('price')::class);
    }

    /** @test */
    public function mapping_roundtrip_restores_searchable_facets(): void
    {
        $props = new NewProperties;
        $props->text('genre')->facetSearchable();

        [$raw, $restored] = $this->restoredProperties($props);
        $genre = $restored->get('genre');

        $this->assertTrue($genre->isFacetSearchable());
        $this->assertTrue($genre->isSortable());
        $this->assertSame($raw['genre']['fields'], $genre->toRaw()['genre']['fields']);
    }

    /** @test */
    public function mapping_roundtrip_preserves_caller_subfields_and_keyword_selection(): void
    {
        $props = new NewProperties;
        $props->text('body')->keyword()
            ->field(new Field('limited', 'keyword', ['ignore_above' => 64]))
            ->field((new Text('prefix'))->unstructuredText()->indexPrefixes(1, 5));
        $props->name('author')->field(new Keyword('custom'));

        [$raw, $restored] = $this->restoredProperties($props);

        $this->assertSame('body.keyword', $restored->get('body')->keywordName());
        foreach (['body', 'author'] as $name) {
            $this->assertSame($raw[$name]['fields'], $restored->get($name)->toRaw()[$name]['fields']);
        }
    }

    /** @test */
    public function mapping_roundtrip_preserves_custom_field_metadata(): void
    {
        $props = new NewProperties;
        $props->keyword('sku')->meta(['source' => 'inventory']);

        [$raw, $restored] = $this->restoredProperties($props);

        $this->assertSame(['source' => 'inventory'], $restored->get('sku')->getMeta());
        $this->assertSame($raw, $restored->toRaw());
    }

    /**
     * @test
     *
     * @dataProvider specializedTextTypes
     */
    public function mapping_roundtrip_preserves_specialized_text_classes(string $method, string $class): void
    {
        $props = new NewProperties;
        $props->$method('body');

        [, $restored] = $this->restoredProperties($props);

        $this->assertSame($class, $restored->get('body')::class);
    }

    public static function specializedTextTypes(): array
    {
        return [
            'long_text' => ['longText', LongText::class],
            'short_text' => ['shortText', ShortText::class],
            'html' => ['html', HTML::class],
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: Properties}
     */
    private function restoredProperties(NewProperties $props): array
    {
        $analysis = new Analysis;
        $built = $props->get($analysis);
        $raw = json_decode(json_encode($built->toRaw()), true);
        $analyzers = [];

        foreach ($analysis->analyzers() as $analyzer) {
            $analyzers[$analyzer->name()] = $analyzer;
        }

        return [$raw, Properties::create($raw, new DefaultAnalyzer, $analyzers, Properties::ROOT_NAME)];
    }

    /**
     * @test
     */
    public function a_field_meta_key_named_one_stays(): void
    {
        $keyword = new Keyword('brand');
        $keyword->meta(['1' => 'rank']);

        $this->assertSame('{"1":"rank"}', json_encode($keyword->getMeta()));
        $this->assertStringContainsString('"1":"rank"', (string) json_encode($keyword->toRaw()));

        $keyword->meta(['2' => 'a', '4' => 'b']);
        $encoded = (string) json_encode($keyword->toRaw());

        $this->assertStringContainsString('"2":"a"', $encoded);
        $this->assertStringContainsString('"4":"b"', $encoded);

        $zero = new Keyword('brand');
        $zero->meta(['0' => 'rank']);
        $this->assertStringContainsString('"0":"rank"', (string) json_encode($zero->toRaw()));

        $price = new Price('cost');
        $price->meta(['1' => 'rank']);
        $this->assertStringContainsString('"1":"rank"', (string) json_encode($price->toRaw()));
    }

    /**
     * @test
     */
    public function a_custom_field_option_named_two_stays(): void
    {
        $field = new Field('title', 'keyword', ['2' => 'kept', 'analyzer' => 'standard']);
        $encoded = (string) json_encode($field->toRaw());

        $this->assertStringContainsString('"2":"kept"', $encoded);
        $this->assertStringContainsString('"analyzer":"standard"', $encoded);
    }

    /**
     * @test
     */
    public function a_field_path_named_eight_stays_on_fields_of_type(): void
    {
        $props = new NewProperties;
        $props->keyword('8');
        $props->keyword('10');
        $props->keyword('sku');
        $props->nested('items', function (NewProperties $props): void {
            $props->keyword('10');
        });

        $fields = $props->get()->fieldsOfType(Keyword::class);

        $this->assertSame('8', $fields->get('8')->fullPath());
        $this->assertSame('10', $fields->get('10')->fullPath());
        $this->assertSame('sku', $fields->get('sku')->fullPath());
        $this->assertSame('items.10', $fields->get('items.10')->fullPath());
    }

    public function test_engine_date_defaults_survive_restoration(): void
    {
        $props = Properties::create(['day' => ['type' => 'date']], new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame('strict_date_optional_time||epoch_millis', $props['day']->toRaw()['day']['format']);
    }

    public function test_fractional_scaling_factor_survives_restoration(): void
    {
        $props = Properties::create(['mass' => ['type' => 'scaled_float', 'scaling_factor' => 2.5]], new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame(2.5, $props['mass']->toRaw()['mass']['scaling_factor']);
    }

    public function test_empty_nested_mapping_restores_a_nested_container(): void
    {
        $props = Properties::create(['items' => ['type' => 'nested']], new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertInstanceOf(Nested::class, $props['items']);
    }

    public function test_builtin_field_analyzer_without_index_settings(): void
    {
        $props = Properties::create(['body' => ['type' => 'text', 'analyzer' => 'english']], new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame('english', $props['body']->searchAnalyzer());
    }

    public function test_restored_query_uses_the_search_time_analyzer(): void
    {
        $props = Properties::create(['body' => ['type' => 'text', 'analyzer' => 'simple', 'search_analyzer' => 'standard']], new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame('standard', $props['body']->queries('foo')[0]->toRaw()['match']['body']['analyzer']);
        $this->assertSame('standard', $props['body']->toRaw()['body']['search_analyzer']);
    }

    public function test_numeric_semantic_names_survive_field_discovery(): void
    {
        $props = new NewProperties;
        $props->image('8')->semantic('clip');
        $props->text('10')->semantic('text');

        $this->assertSame([8, 10], $props->get()->nestedSemanticFields()->keys());
    }

    public function test_numeric_embeddings_tree_segments_are_field_names(): void
    {
        $props = new NewProperties;
        $props->text('8')->semantic('text');
        $mapping = (new Mappings(properties: $props->get()))->toRaw(new Elasticsearch);

        $this->assertArrayHasKey(8, (array) $mapping['properties']->_embeddings['properties']);
    }

    public function test_image_callback_selects_image_similarity(): void
    {
        $props = new NewProperties;
        $vector = $props->image('photo')->newSemantic(fn ($field) => $field->api('clip'))->make();

        $this->assertSame(VectorSimilarity::Euclidean, $vector->similarity);
    }

    public function test_vector_accuracy_accepts_768_dimensions(): void
    {
        $vector = (new NewSemanticField('title'))->accuracy(3, 768)->make();

        $this->assertSame(768, $vector->dims);
        $this->assertSame(42, $vector->m);
        $this->assertSame(260, $vector->efConstruction);
    }

    public function test_vector_accuracy_scales_with_the_final_dimensions(): void
    {
        $vector = (new NewSemanticField('title'))->accuracy(3)->m(32)->dimensions(1536)->make();

        $this->assertSame(1536, $vector->dims);
        $this->assertSame(32, $vector->m);
        $this->assertSame(367, $vector->efConstruction);
        $this->assertSame('m32_efc367_dims1536_cosine_avg', $vector->name);
    }

    public function test_rebuilding_vectors_recomputes_inferred_similarity(): void
    {
        $field = (new NewSemanticField('photo'))->fieldType('image');
        $field->make();

        $this->assertSame(VectorSimilarity::DotProduct, $field->boostedBy('weight')->make()->similarity);
    }

    public function test_keyword_subfield_counts_as_a_text_field_child(): void
    {
        $this->assertTrue((new Text('title'))->unstructuredText()->keyword()->hasFields());
    }

    public function test_plain_text_has_no_sort_or_filter_subfield_name(): void
    {
        $field = (new NewProperties)->text('body');

        $this->assertNull($field->sortableName());
        $this->assertNull($field->filterableName());
    }

    public function test_custom_name_analysis_is_used_for_the_primary_match(): void
    {
        $props = new NewProperties;
        $props->name('author')->withNewAnalyzer(fn (NewAnalyzer $analyzer): NewAnalyzer => $analyzer->tokenizeOnWhitespaces());
        $field = $props->get()['author'];

        $this->assertSame('author_field_analyzer', $field->queries('Ann')[0]->toRaw()['match']['author']['analyzer']);
    }

    public function test_named_properties_propagate_paths_to_all_descendants(): void
    {
        $props = new NewProperties;
        $props->object('address', fn (NewProperties $child): Text => $child->text('street'));

        $this->assertSame('profile.address.street', $props->get(name: 'profile')->get('address.street')->name());
    }

    public function test_generic_field_name_uses_its_container_path(): void
    {
        $props = new NewProperties;
        $props->object('request', fn (NewProperties $child): NewProperties => $child->add(new Field('host', 'ip')));

        $this->assertSame('request.host', $props->get()->get('request.host')->name());
    }

    public function test_mappings_restore_their_own_object_maps(): void
    {
        $fields = new NewProperties;
        $fields->nested('cast', function (NewProperties $cast): void {
            $cast->add(new Field('address', 'ip', ['meta' => ['0' => 'primary']]));
        });
        $mapping = new Mappings(properties: $fields->get(), meta: ['owner' => 'films']);
        $raw = $mapping->toRaw(new Elasticsearch);
        $restored = Mappings::create($raw, []);

        $this->assertEquals($raw, $restored->toRaw(new Elasticsearch));
    }

    public function test_mapping_restores_nested_json_objects(): void
    {
        $raw = json_decode(<<<'JSON'
            {
                "items": {
                    "type": "nested",
                    "properties": {
                        "label": {"type": "text", "fields": {"exact": {"type": "keyword", "ignore_above": 32}}},
                        "embedding": {"type": "dense_vector", "dims": 4, "index_options": {"type": "hnsw", "m": 16}}
                    }
                }
            }
            JSON);

        $properties = Properties::create($raw, new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame('items.label.exact', $properties->get('items.label')->filterableName());
        $this->assertSame(16, $properties->get('items.embedding')->m());
        $this->assertSame(32, $properties->toRaw()['items']['properties']->label['fields']['exact']['ignore_above']);

        $openSearch = json_decode('{"embedding":{"type":"knn_vector","dimension":4,"method":{"name":"hnsw","parameters":{"m":24}}}}');
        $properties = Properties::create($openSearch, new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame(24, $properties->get('embedding')->m());
    }

    public function test_custom_field_metadata_keeps_numeric_names(): void
    {
        $raw = (new Field('server', 'ip', ['meta' => ['0' => 'primary']]))->toRaw();

        $this->assertSame('{"0":"primary"}', json_encode($raw['server']['meta']));
        $empty = (new Field('server', 'ip', ['meta' => []]))->toRaw();
        $this->assertSame('{}', json_encode($empty['server']['meta']));
    }

    /** @test */
    public function built_properties_keep_their_nested_fields_subfields_and_vector_settings(): void
    {
        $builder = new NewProperties;
        $title = $builder->text('title');
        $analyzer = new Analyzer('title_words');
        $title->withAnalyzer($analyzer);
        $vector = $title->semantic('encoder', accuracy: 1, dimensions: 128);
        $container = $builder->nested('reviews', fn (NewProperties $fields): Text => $fields->text('comment'));
        $first = $builder->get();

        $title->makeSortable();
        $analyzer->addFilters([new Trim('trim')]);
        $vector->dimensions(256);
        $container->getProperties()->get('comment')->description('Review text');
        $second = $builder->get();

        $this->assertFalse($first->get('title')->isSortable());
        $this->assertSame([], $first->get('title')->analyzer()->toRaw()['title_words']['filter']);
        $this->assertSame(128, $first->get('title')->vectorFields()->first()->dims());
        $this->assertNull($first->get('reviews.comment')->getDescription());
        $this->assertTrue($second->get('title')->isSortable());
        $this->assertSame(['trim'], $second->get('title')->analyzer()->toRaw()['title_words']['filter']);
        $this->assertSame(256, $second->get('title')->vectorFields()->first()->dims());
        $this->assertSame('Review text', $second->get('reviews.comment')->getDescription());
        $this->assertSame('reviews.comment', $second->get('reviews.comment')->fullPath());
        $this->assertSame('reviews', $second->get('reviews.comment')->nestedPath());
    }

    /** @test */
    public function attaching_properties_to_another_container_keeps_the_first_paths(): void
    {
        $builder = new NewProperties;
        $builder->text('name');

        $properties = $builder->get();
        $author = new Object_('author', $properties);
        $editor = new Nested('editor', $properties);

        $this->assertSame('name', $properties->get('name')->fullPath());
        $this->assertSame('author.name', $author->getProperties()->get('name')->fullPath());
        $this->assertSame('editor.name', $editor->getProperties()->get('name')->fullPath());
        $this->assertSame('editor', $editor->getProperties()->get('name')->nestedPath());
    }

    public function test_literal_dotted_key_reuses_stored_embedding(): void
    {
        $props = new NewProperties;
        $props->object('details', fn (NewProperties $fields): NewSemanticField => $fields->text('title')->semantic(api: 'e', dimensions: 128));

        $schema = $props->get();
        $vectorName = $schema->get('details.title')->vectorFields()->first()->name;
        $vectors = [$vectorName => array_fill(0, 128, 0.5)];
        $processor = (new DocumentProcessor($schema))->apis(['e' => $this->createStub(EmbeddingsApi::class)]);
        $doc = $processor->populateEmbeddings(new Document(['_embeddings' => ['details.title' => $vectors]]));
        $this->assertSame($vectors, $doc->_source['_embeddings']['details']['title'] ?? null);
    }

    public function test_literal_dotted_container_lookup(): void
    {
        $props = new NewProperties;
        $props->object('host.info', fn (NewProperties $fields): Keyword => $fields->keyword('os.name'));
        $this->assertSame('host.info.os.name', $props->get()->get('host.info.os.name')?->fullPath());
    }

    public function test_literal_dotted_date_source(): void
    {
        $props = new NewProperties;
        $props->object('event', fn (NewProperties $fields): DateField => $fields->date('day'));

        $processor = new DocumentProcessor($props->get());
        $doc = $processor->formatDateTimeFields(new Document(['event.day' => new DateTime('2024-04-01')]));
        $this->assertSame('2024-04-01', $doc->_source['event.day']);
    }

    public function test_literal_dotted_source_validation(): void
    {
        $props = new NewProperties;
        $props->object('item', fn (NewProperties $fields): Number => $fields->integer('count'));

        $processor = new DocumentProcessor($props->get());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('item.count');
        $processor->validateFields(new Document(['item.count' => 'many']));
    }

    public function test_literal_dot_in_combo_destination(): void
    {
        $props = new NewProperties;
        $props->combo('summary.text', ['title']);

        $doc = (new DocumentProcessor($props->get()))->populateComboFields(new Document(['title' => 'Atlas']));
        $this->assertSame('Atlas', $doc->_source['summary.text'] ?? null);
    }

    public function test_restored_date_range_preserves_its_bound_format(): void
    {
        $mapping = ['availability' => ['type' => 'date_range', 'format' => 'dd/MM/uuuu']];
        $restored = Properties::create($mapping, new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $this->assertSame($mapping['availability']['format'], $restored['availability']->toRaw()['availability']['format'] ?? null);
    }
}
