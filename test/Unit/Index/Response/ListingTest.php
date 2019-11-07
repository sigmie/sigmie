<?php

namespace Sigma\Test\Unit\Index\Responses;

use PHPUnit\Framework\TestCase;
use Sigma\Index\IndexCollection;
use Sigma\Index\Response\Insert;
use Sigma\Index\Response\Listing;
use Sigma\Index\Response\Remove;

class ListingTest extends TestCase
{
    private $response;

    public function setUp(): void
    {
        $this->response = new Listing();
    }

    /**
     * @test
     */
    public function result(): void
    {
        $result = $this->response->result(['foo', 'bar'], function () { });

        $this->assertInstanceOf(IndexCollection::class, $result);
    }
}
