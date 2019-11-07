<?php


namespace Sigma\Test\Unit\Common;

use PHPUnit\Framework\TestCase;
use Sigma\Sigma;
use Sigma\Common\HasEvents;

class HasEventsTest extends TestCase
{
    /**
     * @test
     */
    public function HasEvents(): void
    {
        $mock = $this->getMockForTrait(HasEvents::class);
        $events = $mock::getSubscribedEvents();

        $this->assertIsArray($events);
    }
}
