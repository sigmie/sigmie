<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use WithRunningInternalCluster;

    /**
     * @test
     */
    public function list_returns_view(): void
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $this->assertInertiaViewExists('notification/list');

        $this->get(route('notification.list'))->assertInertia('notification/list');
    }
}
