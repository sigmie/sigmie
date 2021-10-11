<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Mappings\Blueprint;
use Sigmie\Base\Search\Clauses\Boolean;
use Sigmie\Base\Search\Compound\Boolean as CompoundBoolean;
use Sigmie\Base\Search\Queries\Compound\Boolean as QueriesCompoundBoolean;
use Sigmie\Base\Search\QueryBuilder;
use Sigmie\Testing\TestCase;

class QueryTest extends TestCase
{
    use Index, Search, Explain;

    /**
     * @test
     */
    public function default_analyzer_even_if_no_text_field_mapping()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('title', keyword: true)->unstructuredText();
                $blueprint->text('description')->unstructuredText();
                $blueprint->date('created_at')->format('yyyy-MM-dd');
                $blueprint->bool('is_valid');
                $blueprint->number('count')->integer();
                $blueprint->number('avg')->float();
            })
            ->create();

        $collection = $this->sigmie->collect($alias);

        $docs = [
            new Document([
                'title' => 'The story of Nemo',
                'description' => 'The father of Nemo began his journey of finding his son.',
                'created_at' => '1994-05-09',
                'is_valid' => true,
                'count' => 5,
                'avg' => 73.3,
            ], '1'),
            new Document([
                'title' => 'Peter Pan and Captain Hook',
                'description' => 'And after this Peter pan woke up in his room.',
                'created_at' => '1995-07-26',
                'is_valid' => false,
                'count' => 233,
                'avg' => 120.3,
            ], '2'),
        ];

        $collection->merge($docs);

        $query = $this->sigmie->search($alias)->bool(function (QueriesCompoundBoolean $boolean) {

            // $boolean->filter->match('title', 'Peter Pan and Captain Hook');
            $boolean->filter->matchAll();

        })->sortAsc('title.keyword')
          ->fields(['title'])
          ->from(0)
          ->size(2)
          ->get();

          dd($query);

        dd($res::class);
        dd($res->json());
    }
}
