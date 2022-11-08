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
use Sigmie\Mappings\Blueprint;

use function Sigmie\Functions\random_letters;

class MappingsTest extends TestCase
{
    /**
     * @test
     */
    public function analyzers_collection()
    {
        $blueprint = new Blueprint();
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
