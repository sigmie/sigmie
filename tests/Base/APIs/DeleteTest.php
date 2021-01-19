<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs;

use Sigmie\Base\APIs\Calls\Delete as DeleteAPI;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Index\Index;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\TestConnection;
use Sigmie\Testing\TestIndex;

class DeleteTest extends TestCase
{
    use TestConnection, DeleteAPI, TestIndex;

    /**
     * @test
     */
    public function delete_api_call(): void
    {
        $index = $this->getTestIndex();

        $index->addDocument(new Document(['foo' => 'bar'], '0'));

        $this->assertCount(1, $index);

        $this->deleteAPICall('0');

        $this->assertCount(0, $index);
    }

    protected function index(): Index
    {
        return $this->getTestIndex();
    }
}
