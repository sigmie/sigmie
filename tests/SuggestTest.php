<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Document\Document;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\Suggest;
use Sigmie\Testing\TestCase;

class SuggestTest extends TestCase
{
    use Explain;
    use Index;
    use Search;

    /**
     * @test
     */
    public function term(): void
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)
            ->mapping(function (NewProperties $blueprint): void {
                $blueprint->text('title-text');
            })
            ->lowercase()
            ->trim()
            ->create();

        $collection = $this->sigmie->collect($name, true);

        $docs = [
            new Document([
                'title-text' => 'Star Wars',
            ]),
            new Document([
                'title-text' => 'Lorem',
            ]),
            new Document([
                'title-text' => 'Star Trek',
            ]),
            new Document([
                'title-text' => 'Starbucks',
            ]),
            new Document([
                'title-text' => 'Starry Night',
            ]),
        ];

        $collection->merge($docs);

        $suggest = new Suggest;
        $suggest->term(name: 'my-term')
            ->field('title-text')
            ->text('stary');

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->suggest(
                $suggest
            )
            ->get();

        $suggestions = array_map(fn ($value) => $value['text'], $res->json('suggest.my-term.0.options'));

        $this->assertEquals([
            'starry',
            'star',
        ], $suggestions);
    }

    /**
     * @test
     */
    public function phrase(): void
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)
            ->mapping(function (NewProperties $blueprint): void {
                $blueprint->text('title-phrase')->withNewAnalyzer(function (NewAnalyzer $newAnalyzer): void {
                    $newAnalyzer
                        ->lowercase()
                        ->shingle(2, 3);
                });
            })
            ->create();

        $collection = $this->sigmie->collect($name, true);

        $docs = [
            new Document([
                'title-phrase' => 'nobel prize',
            ]),
            new Document([
                'title-phrase' => 'nobel prize',
            ]),
            new Document([
                'title-phrase' => 'nobel warriors',
            ]),
            new Document([
                'title-phrase' => 'nobel garden',
            ]),
            new Document([
                'title-phrase' => 'nobel night',
            ]),
        ];

        $collection->merge($docs);

        $suggest = new Suggest;
        $suggest->phrase(name: 'my-phrase')
            ->field('title-phrase')
            ->highlight('<em>', '</em>')
            ->size(3)
            ->ngramSize(3)
            ->text('noble garden');

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->suggest(
                $suggest
            )
            ->get();

        $suggestions = array_map(fn ($value) => $value['text'], $res->json('suggest.my-phrase.0.options'));
        $highlighted = array_map(fn ($value) => $value['highlighted'], $res->json('suggest.my-phrase.0.options'));

        $this->assertEquals([
            '<em>nobel</em> garden',
        ], $highlighted);
        $this->assertEquals([
            'nobel garden',
        ], $suggestions);
    }

    /**
     * @test
     */
    public function completion(): void
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)
            ->mapping(function (NewProperties $blueprint): void {
                $blueprint->text('title-completion')->completion();
            })
            ->create();

        $collection = $this->sigmie->collect($name, true);

        $docs = [
            new Document([
                'title-completion' => 'Star Wars',
            ]),
            new Document([
                'title-completion' => 'Lorem',
            ]),
            new Document([
                'title-completion' => 'Star Trek',
            ]),
            new Document([
                'title-completion' => 'Starbucks',
            ]),
            new Document([
                'title-completion' => 'Starry Night',
            ]),
        ];

        $collection->merge($docs);

        $suggest = new Suggest;
        $suggest->completion(name: 'my-completion')
            ->field('title-completion')
            ->prefix('st');

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->suggest(
                $suggest
            )
            ->get();

        $suggestions = array_map(fn ($value) => $value['text'], $res->json('suggest.my-completion.0.options'));

        $this->assertEquals([
            'Star Trek',
            'Star Wars',
            'Starbucks',
            'Starry Night',
        ], $suggestions);
    }
}
