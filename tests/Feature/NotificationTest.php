<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CleanNotifications;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Fakes\FakeNotification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function index_returns_json_with_users_notifications()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user);

        $user->notify(new FakeNotification());

        $this->get('/ajax/notification')->assertJsonCount(1);
    }

    /**
     * @test
     */
    public function show_returns_notification_data()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user);

        $user->notify(new FakeNotification());

        $id = $user->notifications->first()->id;

        $response = $this->get("ajax/notification/{$id}");

        $this->assertContains($id, $response->json());
    }

    /**
     * @test
     */
    public function update_marks_notification_as_read()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user);

        $user->notify(new FakeNotification());

        $id = $user->notifications->first()->id;

        $this->put("ajax/notification/{$id}");

        $response = $this->get("ajax/notification/{$id}");

        $read_at = $response->json('read_at');

        $this->assertNotNull($read_at);
    }

    /**
     * @test
     */
    public function remove_notification_older_than_a_month()
    {
        Carbon::setTestNow('2030-01-01');

        $user = factory(User::class)->create();

        $user->notify(new FakeNotification());

        $id = $user->notifications->first()->id;

        Carbon::setTestNow('2030-02-02');

        $job = new CleanNotifications();

        $job->handle();

        $this->assertDatabaseMissing('notifications', ['id' => $id]);
    }

    /**
     * @test
     */
    public function index_doesnt_return_notifications_older_than_a_week()
    {
        Carbon::setTestNow('2030-01-01');

        $user = factory(User::class)->create();

        $this->actingAs($user);

        $user->notify(new FakeNotification());

        Carbon::setTestNow('2030-01-09');

        $response = $this->get('/ajax/notification');

        $response->assertJsonCount(0);
    }

    /**
     * @test
     */
    public function index_returns_notifications_newer_than_a_week()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user);

        $user->notify(new FakeNotification());

        $response = $this->get('/ajax/notification');

        $response->assertJsonCount(1);
    }

    /**
     * @test
     */
    public function notifications_index_returns_unauthrorized_json_to_guest()
    {
        $this->getJson('/ajax/notification')->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
