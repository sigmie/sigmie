<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\APIs\Calls\Index;
use Sigmie\Base\Exceptions\ElasticsearchException;

trait Assertions
{
    use Index;

    public function assertIndexExists(string $name)
    {
        try {
            $res = $this->indexAPICall("/{$name}", 'HEAD');
            $code = $res->code();
        } catch (ElasticsearchException $e) {
            $code = $e->getCode();
        }

        $this->assertEquals(200, $code, "Failed to assert that index {$name} exists.");
    }

    public function assertIndexNotExists(string $name)
    {
        try {
            $res = $this->indexAPICall("/{$name}", 'HEAD');
            $code = $res->code();
        } catch (ElasticsearchException $e) {
            $code = $e->getCode();
        }

        $this->assertEquals(404, $code, "Failed to assert that index {$name} doesn't exists.");
    }

    abstract public static function assertContains($needle, iterable $haystack, string $message = ''): void;

    abstract public static function assertArrayHasKey($key, $array, string $message = ''): void;

    abstract public static function assertEquals($expected, $actual, string $message = ''): void;

    abstract public static function assertNotContains($needle, iterable $haystack, string $message = ''): void;

    protected function indexData(string $name): array
    {
        $json = $this->indexAPICall($name, 'GET')->json();
        $indexName = array_key_first($json);

        return $json[$indexName];
    }

    protected function assertFilterEquals(string $index, string $filter, array $value)
    {
        $data = $this->indexData($index);

        $this->assertFilterExists($index, $filter);
        $this->assertEquals($value, $data['settings']['index']['analysis']['filter'][$filter]);
    }

    protected function assertCharFilterEquals(string $index, string $charFilter, array $value)
    {
        $data = $this->indexData($index);

        $this->assertCharFilterExists($index, $charFilter);
        $this->assertEquals($value, $data['settings']['index']['analysis']['char_filter'][$charFilter]);
    }

    protected function assertTokenizerEquals(string $index, string $tokenizer, array $value)
    {
        $data = $this->indexData($index);

        $this->assertTokenizerExists($index, $tokenizer);
        $this->assertEquals($value, $data['settings']['index']['analysis']['tokenizer'][$tokenizer]);
    }

    protected function assertFilterHasStemming(string $index, string $filter, array $rules)
    {
        $data = $this->indexData($index);

        $this->assertEquals($rules, $data['settings']['index']['analysis']['filter'][$filter]['rules']);
    }

    protected function assertFilterHasStopwords(string $index, string $filter, array $stopwords)
    {
        $data = $this->indexData($index);

        $this->assertEquals($stopwords, $data['settings']['index']['analysis']['filter'][$filter]['stopwords']);
    }

    protected function assertFilterHasSynonyms(string $index, string $filter, array $synonyms)
    {
        $data = $this->indexData($index);

        $this->assertEquals($synonyms, $data['settings']['index']['analysis']['filter'][$filter]['synonyms']);
    }

    protected function assertFilterExists(string $index, string $filter)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey($filter, $data['settings']['index']['analysis']['filter']);
    }

    protected function assertCharFilterExists(string $index, string $charFilter)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey($charFilter, $data['settings']['index']['analysis']['char_filter']);
    }

    protected function assertTokenizerExists(string $index, string $tokenizer)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey($tokenizer, $data['settings']['index']['analysis']['tokenizer']);
    }

    protected function assertAnalyzerHasCharFilter(string $index, string $analyzer, string $charFilter)
    {
        $data = $this->indexData($index);

        $this->assertContains($charFilter, $data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter']);
    }

    protected function assertAnalyzerHasNotFilter(string $index, string $analyzer, string $filter)
    {
        $data = $this->indexData($index);

        $this->assertNotContains($filter, $data['settings']['index']['analysis']['analyzer'][$analyzer]['filter']);
    }

    protected function assertAnalyzerHasNotCharFilter(string $index, string $analyzer, string $charFilter)
    {
        $data = $this->indexData($index);

        $this->assertNotContains($charFilter, $data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter']);
    }

    protected function assertAnalyzerHasFilter(string $index, string $analyzer, string $filter)
    {
        $data = $this->indexData($index);

        $this->assertContains($filter, $data['settings']['index']['analysis']['analyzer'][$analyzer]['filter']);
    }

    protected function assertAnalyzerTokenizerIs(string $index, string $analyzer, string $tokenizer)
    {
        $data = $this->indexData($index);

        $this->assertEquals($tokenizer, $data['settings']['index']['analysis']['analyzer'][$analyzer]['tokenizer']);
    }

    protected function assertIndexAnalyzerTokenizerIsWordBoundaries(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertIndexHasAnalyzer($index, $analyzer);
        $this->assertEquals('standard', $data['settings']['index']['analysis']['analyzer'][$analyzer]['tokenizer']);
    }

    protected function assertIndexAnalyzerTokenizerIsWhitespaces(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertIndexHasAnalyzer($index, $analyzer);
        $this->assertEquals('whitespace', $data['settings']['index']['analysis']['analyzer'][$analyzer]['tokenizer']);
    }

    protected function assertIndexAnalyzerFilterIsEmpty(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertEmpty($data['settings']['index']['analysis']['analyzer'][$analyzer]['filter']);
    }

    protected function assertIndexAnalyzerHasTokenizer(string $index, string $analyzer, string $tokenizer)
    {
        $data = $this->indexData($index);

        $this->assertEquals($tokenizer, $data['settings']['index']['analysis']['analyzer'][$analyzer]['tokenizer']);
    }

    protected function assertIndexAnalyzerCharFilterIsEmpty(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertEmpty($data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter']);
    }

    protected function assertIndexHasAnalyzer(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey('analysis', $data['settings']['index']);
        $this->assertArrayHasKey('analyzer', $data['settings']['index']['analysis']);
        $this->assertArrayHasKey($analyzer, $data['settings']['index']['analysis']['analyzer']);
    }

    protected function assertIndexHasMappings(string $index)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey('mappings', $data);
    }

    protected function assertIndexPropertyExists(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey($property, $data['mappings']['properties']);
    }

    protected function assertIndexPropertyIsDate(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'date');
    }

    protected function assertIndexPropertyIsSearchAsYouType(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'search_as_you_type');
    }

    protected function assertIndexPropertyIsUnstructuredText(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'text');
    }

    protected function assertIndexPropertyIsInteger(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'integer');
    }

    protected function assertIndexPropertyIsFloat(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'float');
    }

    protected function assertIndexPropertyIsBoolean(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'boolean');
    }
}
