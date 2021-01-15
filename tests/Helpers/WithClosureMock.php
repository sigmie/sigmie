<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Closure;
use stdClass;
use PHPUnit\Framework\MockObject\MockObject;

trait WithClosureMock
{
    /**
     * @var Closure|MockObject
     */
    private $closureMock;

    /**
     * @var MockObject
     *
     * @method null closure()
     */
    private $callableMock;

    public function withClosureMock()
    {
        $this->callableMock = $this->getMockBuilder(stdClass::class)->addMethods(['closure'])->getMock();

        $this->closureMock = fn (...$args) => $this->callableMock->closure(...$args);
    }

    public function expectClosureMockCalledWith(...$args)
    {
        $this->callableMock->expects($this->once())
            ->method('closure')
            ->with(...$args);
    }
}
