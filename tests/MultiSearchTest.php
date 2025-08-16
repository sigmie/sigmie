<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Http\Promise\Promise;
use Sigmie\Document\Document;
use Sigmie\Languages\English\English;
use Sigmie\Languages\German\German;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Price;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class MultiSearchTest extends TestCase
{
    /**
     * @test
     */
    public function weighted_query_string()
    {
        $indexName1 = uniqid();
        $indexName2 = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName1)
            ->properties($blueprint)
            ->create();

        $this->sigmie->newIndex($indexName2)
            ->properties($blueprint)
            ->create();

        $collected1 = $this->sigmie->collect($indexName1, refresh: true);
        $collected2 = $this->sigmie->collect($indexName2, refresh: true);

        $collected1->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Donald']),
        ]);

        $collected2->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Donald']),
        ]);

        $multisearch = $this->sigmie->newMultiSearch();

        $res = $multisearch->newSearch($indexName2)
            ->properties($blueprint)
            ->queryString('Goofy')->get();

        $multisearch->newSearch($indexName1)
            ->properties($blueprint)
            ->name('search1')
            ->queryString('Mickey');

        $multisearch->newSearch($indexName2)
            ->properties($blueprint)
            ->queryString('Goofy');

        $res = $multisearch->get();

        dd($res['search1']->json());

        $formatted = $res->get('search1')->json();

        dd($formatted);
    }
}
