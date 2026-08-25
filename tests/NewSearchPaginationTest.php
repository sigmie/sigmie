<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use PHPUnit\Framework\TestCase;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewSearch;

class NewSearchPaginationTest extends TestCase
{
    /**
     * @test
     */
    public function from_sets_the_elasticsearch_offset(): void
    {
        $search = $this->newSearch()
            ->from(40)
            ->size(20);

        $raw = $search->makeSearch()->toRaw();

        $this->assertSame(40, $raw['from']);
        $this->assertSame(20, $raw['size']);
    }

    /**
     * @test
     */
    public function page_sets_from_and_size_on_the_elasticsearch_body(): void
    {
        $search = $this->newSearch()
            ->page(3, 20);

        $raw = $search->makeSearch()->toRaw();

        $this->assertSame(40, $raw['from']);
        $this->assertSame(20, $raw['size']);
    }

    private function newSearch(): NewSearch
    {
        $http = $this->createMock(JSONClientInterface::class);
        $search = new NewSearch(new ElasticsearchConnection($http));
        $search->index('products');
        $search->properties(new NewProperties);

        return $search;
    }
}
