<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\Subscription\UserWasSubscribed;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\TestCase;

class UserWasSubscribedTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function broadcasted_on_private_channel(): void
    {
        $userId = 1;

        $event = new UserWasSubscribed($userId);

        $this->assertEquals($userId, $event->userId);
        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn());
        $this->assertEquals($event->broadcastOn()->name, 'private-user.1');
    }
}
