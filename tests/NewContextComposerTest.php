<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewContextComposer;
use Sigmie\Testing\TestCase;

class NewContextComposerTest extends TestCase
{
    /**
     * @test
     */
    public function it_composes_context_from_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->text('body');
        $blueprint->number('rank');

        $this->sigmie->newIndex($indexName)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Alpha Guide', 'body' => 'First guide', 'rank' => 1]),
                new Document(['title' => 'Beta Guide', 'body' => 'Second guide', 'rank' => 2]),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Guide')
            ->sort('rank:asc')
            ->hits();

        $this->assertSame('Alpha Guide', $hits[0]->_source['title']);
        $this->assertSame('Beta Guide', $hits[1]->_source['title']);

        $allFieldsContext = (new NewContextComposer)->compose([$hits[0]]);

        $this->assertStringContainsString('"body":"First guide"', $allFieldsContext);

        $context = (new NewContextComposer)
            ->fields(['title', 'missing'])
            ->separator(' | ')
            ->compose($hits);

        $this->assertSame('{"title":"Alpha Guide"} | {"title":"Beta Guide"}', $context);
    }

    /**
     * @test
     */
    public function it_composes_custom_context_from_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Custom Context']),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Custom')
            ->hits();

        $this->assertSame('Custom Context', $hits[0]->_source['title']);

        $context = (new NewContextComposer)
            ->formatter(fn (Hit $hit): string => sprintf('title=%s', $hit->_source['title']))
            ->compose($hits);

        $this->assertSame('title=Custom Context', $context);
    }
}
