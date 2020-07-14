<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\ProjectValidationController;
use App\Models\Project;
use App\Rules\ValidProvider;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ProjectValidationControllerTest extends TestCase
{
    /**
     * @var ProjectValidationController
     */
    private $controller;

    /**
     * @var Rule|MockObject
     */
    private $ruleMock;

    /**
     * @var Request|MockObject
     */
    private $requestMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->requestMock = $this->createMock(Request::class);

        $this->ruleMock = $this->createMock(ValidProvider::class);

        $this->controller = new ProjectValidationController($this->ruleMock);
    }

    /**
     * @test
     */
    public function valid_is_false_if_rule_fails(): void
    {
        $this->ruleMock->method('passes')->willReturn(false);

        $response = $this->controller->provider($this->requestMock);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertFalse($response->getData(true)['valid']);
    }

    /**
     * @test
     */
    public function valid_is_true_if_rule_passes(): void
    {
        $this->ruleMock->method('passes')->willReturn(true);

        $response = $this->controller->provider($this->requestMock);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertTrue($response->getData(true)['valid']);
    }
}
