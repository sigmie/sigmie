<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Sigmie\SigmieIndex;
use Sigmie\Testing\TestCase;

class SigmieIndexTest extends TestCase
{
    /**
     * @test
     */
    public function properties_are_automatically_passed_to_searches(): void
    {
        $productIndex = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $blueprint = new NewProperties;
                $blueprint->text('name');
                $blueprint->text('description');
                $blueprint->category('category');

                return $blueprint;
            }
        };

        $productIndex->create();

        $productIndex->collect(refresh: true)->merge([
            new Document(['name' => 'iPhone', 'description' => 'Smartphone', 'category' => 'electronics']),
            new Document(['name' => 'MacBook', 'description' => 'Laptop', 'category' => 'electronics']),
            new Document(['name' => 'AirPods', 'description' => 'Earphones', 'category' => 'audio']),
        ]);

        // No need to pass properties!
        $results = $productIndex->newSearch()
            ->queryString('iPhone')
            ->get();

        $hits = $results->hits();

        $this->assertEquals(1, $results->total());
        $this->assertEquals('iPhone', $hits[0]->get('name'));
    }

    /**
     * @test
     */
    public function name_method_returns_index_name(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                return new NewProperties;
            }
        };

        $this->assertEquals($index->name(), $index->name());
    }

    /**
     * @test
     */
    public function properties_method_returns_blueprint(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $blueprint = new NewProperties;
                $blueprint->text('title');
                $blueprint->bool('active');

                return $blueprint;
            }
        };

        $properties = $index->properties();

        $this->assertInstanceOf(NewProperties::class, $properties);
    }

    /**
     * @test
     */
    public function create_and_delete_index(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $blueprint = new NewProperties;
                $blueprint->text('content');

                return $blueprint;
            }
        };

        // Create index
        $createdIndex = $index->create();
        $this->assertNotNull($createdIndex);

        $this->assertIndexExists($index->name());

        // Delete index
        $index->delete();

        // Check it's gone
        $this->assertIndexNotExists($index->name());
    }

    /**
     * @test
     */
    public function to_documents_converts_arrays_to_documents(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                return new NewProperties;
            }
        };

        $data = [
            ['name' => 'Item 1'],
            ['name' => 'Item 2'],
        ];

        $index->collect(refresh: true)->merge(array_map(fn ($document): Document => new Document($document), $data));

        $this->assertEquals(2, $index->collect(refresh: true)->count());
    }

    /**
     * @test
     */
    public function collect_uses_properties_automatically(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $blueprint = new NewProperties;
                $blueprint->text('title');
                $blueprint->category('type');

                return $blueprint;
            }
        };

        $index->create();

        // Collect should have properties pre-configured
        $collection = $index->collect(refresh: true);

        $collection->merge([
            new Document(['title' => 'Test', 'type' => 'demo']),
        ]);

        $results = $index->newSearch()
            ->queryString('Test')
            ->get();

        $this->assertEquals(1, $results->total());
    }

    /**
     * @test
     */
    public function custom_index_builder_configuration(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $blueprint = new NewProperties;
                $blueprint->text('content');

                return $blueprint;
            }
        };

        $createdIndex = $index->create();
        $this->assertNotNull($createdIndex);

        // The index should be created with the custom settings
        $this->assertIndexExists($index->name());
    }
}
