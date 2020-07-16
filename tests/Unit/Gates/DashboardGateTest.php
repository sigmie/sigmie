<?php

declare(strict_types=1);

namespace Tests\Unit\Gates;

use App\Models\Project;
use App\Models\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Gates\DashboardGate;

class DashboardGateTest extends TestCase
{
    /**
     * @var DashboardGate
     */
    private $gate;

    /**
     * @var Project|MockObject
     */
    private $projectMock;
    /**
     * @var User|MockObject
     */
    private  $userMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->projectMock = $this->createMock(Project::class);

        $this->userMock = $this->createMock(User::class);

        $this->gate = new DashboardGate;
    }

    /**
     * @test
     */
    public function view_returns_true_on_same_user_id(): void
    {
        $this->userMock->method('getAttribute')->willReturnMap([['id', 1]]);
        $this->projectMock->method('getAttribute')->willReturnMap([['user_id', 1]]);

        $this->assertTrue($this->gate->view($this->userMock, $this->projectMock));
    }

    /**
     * @test
     */
    public function view_returns_false_on_different_user_id(): void
    {
        $this->userMock->method('getAttribute')->willReturnMap([['id', 3]]);
        $this->projectMock->method('getAttribute')->willReturnMap([['user_id', 1]]);

        $this->assertFalse($this->gate->view($this->userMock, $this->projectMock));
    }
}
