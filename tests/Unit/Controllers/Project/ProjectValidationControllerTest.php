<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Project;

use App\Http\Controllers\Project\ValidationController;
use App\Rules\ValidProvider;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ProjectValidationControllerTest extends TestCase
{
    /**
     * @var ValidationController
     */
    private $controller;

    /**
     * @var MockObject|Rule
     */
    private $ruleMock;

    /**
     * @var MockObject|Request
     */
    private $requestMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->requestMock = $this->createMock(Request::class);

        $this->ruleMock = $this->createMock(ValidProvider::class);

        $this->controller = new ValidationController($this->ruleMock);
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
