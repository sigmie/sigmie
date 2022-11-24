<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Exception;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\APIs\Index;
use Sigmie\Index\Mappings;
use Sigmie\Testing\TestCase;
use Sigmie\Mappings\NewProperties;
use Sigmie\Document\Document;

use function Sigmie\Functions\random_letters;

class MappingsTest extends TestCase
{
    // /**
    //  * @test
    //  */
    // public function noop_tokenizer()
    // {
    //     $indexName = uniqid();

    //     $index = $this->sigmie
    //         ->newIndex($indexName)
    //         // ->patternReplace(":D|:\)", 'happy')
    //         // ->mapChars([':)'=> 'happy'])
    //         ->stripHTML()
    //         // ->dontTokenize()
    //         ->tokenizeOnWordBoundaries()
    //         // ->tokenizeOnPattern(',')
    //         // ->tokenizeOnPatternMatch("'.*'")
    //         // ->dontTokenize()
    //         // ->tokenizeOnWhiteSpaces()
    //         // ->dontTokenize()
    //         ->create();

    //     $index = $this->sigmie->collect($indexName, refresh: true);

    //     $res = $this->analyzeAPICall($indexName, "<span>Some people are worth melting for.</span>", 'default');

    //     $tokens = array_map(fn ($token) => $token['token'], $res->json('tokens'));

    //     dd($tokens);
    //     $props = new NewProperties();
    // }

    /**
     * @test
     */
    public function address_analyze()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->address();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $res = $this->analyzeAPICall($indexName, 'Hohn Doe 28, 58511', 'address_field_analyzer');

        $tokens = array_map(fn ($token) => $token['token'], $res->json('tokens'));

        $this->assertEquals(['hohn doe 28', '58511'], $tokens);
    }

    /**
     * @test
     */
    public function year()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->searchableNumber('year');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['year' => '2027']),
            new Document(['year' => '1821']),
            new Document(['year' => '1947']),
            new Document(['year' => '1821']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('1821')
            ->fields(['year'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function category()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'horror']),
            new Document(['category' => 'sport']),
            new Document(['category' => 'action']),
            new Document(['category' => 'drama']),
            new Document(['category' => 'drama']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('dra')
            ->fields(['category'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function active()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->active();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['active' => true]),
            new Document(['active' => true]),
            new Document(['active' => false]),
            new Document(['active' => true]),
            new Document(['active' => true]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('active')
            ->fields(['active'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(4, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('inactive')
            ->fields(['active'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function searchable_number()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->searchableNumber('number');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['number' => '0020020202']),
            new Document(['number' => '2353051500']),
            new Document(['number' => '9999999']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('999')
            ->fields(['number'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertEquals('9999999', $hits[0]['_source']['number']);
        $this->assertCount(1, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('2353151500')
            ->typoTolerance()
            ->typoTolerantAttributes(['number'])
            ->fields(['number'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertEquals('2353051500', $hits[0]['_source']['number']);
        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function email()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->email('email');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['email' => 'john.doe@gmail.com']),
            new Document(['email' => 'marc@hotmail.com']),
            new Document(['email' => 'phill.braun@outlook.com']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('doe')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertEquals('john.doe@gmail.com', $hits[0]['_source']['email']);
        $this->assertCount(1, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('.com')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(3, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('bra')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertEquals('phill.braun@outlook.com', $hits[0]['_source']['email']);
    }

    /**
     * @test
     */
    public function analyzers_collection()
    {
        $blueprint = new NewProperties();
        $defaultAnalyzer = new DefaultAnalyzer(new WordBoundaries());
        $analyzer = new Analyzer('bar', new WordBoundaries());

        $blueprint->text('title')->searchAsYouType();
        $blueprint->text('content')->unstructuredText($analyzer);
        $blueprint->number('adults')->integer();
        $blueprint->number('price')->float();
        $blueprint->date('created_at');
        $blueprint->bool('is_valid');

        $properties = $blueprint();
        $mappings = new Mappings($defaultAnalyzer, $properties);

        $analyzers = $mappings->analyzers();

        $this->assertContains($defaultAnalyzer, $analyzers);
        $this->assertContains($analyzer, $analyzers);
    }
}
