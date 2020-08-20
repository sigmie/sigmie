<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\ClusterValidationController;
use App\Http\Controllers\UserValidationController;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ClusterValidationControllerTest extends TestCase
{
    /**
     * @var UserValidationController
     */
    private $controller;

    /**
     * @var ClusterRepository|MockObject
     */
    private $clusterRepositoryMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->clusterRepositoryMock = $this->createMock(ClusterRepository::class);

        $this->controller = new ClusterValidationController($this->clusterRepositoryMock);
    }

    /**
     * @test
     */
    public function name_finds_trashed_by_name_and_returns_false_if_not_found(): void
    {
        $this->clusterRepositoryMock->method('findOneTrashedBy')->willReturn(null);

        $this->clusterRepositoryMock->expects($this->once())->method('findOneTrashedBy')->with('name', 'foo');

        $response = $this->controller->name('foo');

        $this->assertTrue($response->getData(true)['valid']);
    }

    /**
     * @test
     */
    public function name_finds_trashed_by_name_and_returns_true_if_records_was_found(): void
    {
        $this->clusterRepositoryMock->method('findOneTrashedBy')->willReturn(new Cluster());

        $this->clusterRepositoryMock->expects($this->once())->method('findOneTrashedBy')->with('name', 'foo');

        $response = $this->controller->name('foo');

        $this->assertFalse($response->getData(true)['valid']);
    }
}
