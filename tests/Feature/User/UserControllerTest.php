<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Models\IndexingPlan;
use App\Models\User;
use Google_Service_Spanner_GetDatabaseDdlResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Paddle\Cashier;
use Sigmie\App\Core\DNS\Contracts\Provider as DNSProvider;
use LogicException;
use Mockery\MockInterface;
use Sigmie\App\Core\DNS\Records\ARecord;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Tests\Helpers\WithIndexingPlan;
use Tests\Helpers\WithRunningCluster;
use Tests\Helpers\WithSubscribedUser;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use WithSubscribedUser, WithRunningCluster, WithIndexingPlan;

    /**
     * @test
     */
    public function destroy()
    {
        $this->withSubscribedUser();
        $this->withIndexingPlan($this->user);

        $this->actingAs($this->user);

        Http::fake(function () {
            return Http::response(['success' => true], 200); // Paddle unsubscribe response
        });

        $this->mock(DNSProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('removeRecord')->once();
        });

        $subscriptionId = $this->user->subscription(config('services.paddle.plan_name'))->paddle_id;

        ray($res = $this->delete(route('user.destroy', ['user' => $this->user->id])));

        //Cancel subscription request
        Http::assertSent(function (Request $request) use ($subscriptionId) {
            return
                $request->url() == Cashier::vendorsUrl() . '/api/2.0/subscription/users_cancel' &&
                $request['vendor_id'] == config('cashier.vendor_id') &&
                $request["vendor_auth_code"] == config('cashier.vendor_auth_code') &&
                $request["subscription_id"] == $subscriptionId;
        });

        $user = DB::table('users')->where('id', '=', $this->user->id)->get();
        $projects = DB::table('projects')->where('id', '=', $this->project->id)->get();
        $plans = DB::table('indexing_plans')->where('cluster_id', '=', $this->cluster->id)->get();
        $subscriptions = DB::table('subscriptions')->where('billable_id', '=', $this->user->id)->where('billable_type', '=', 'user')->get();
        $receipts = DB::table('receipts')->where('billable_id', '=', $this->user->id)->where('billable_type', '=', 'user')->get();
        $tokens = DB::table('cluster_tokens')->where('tokenable_id', '=', $this->user->id)->where('tokenable_type', '=', 'user')->get();
        $clusters = DB::table('clusters')->where('project_id', '=', $this->project->id)->get();

        $this->assertEmpty($clusters);
        $this->assertEmpty($tokens);
        $this->assertEmpty($receipts);
        $this->assertEmpty($subscriptions);
        $this->assertEmpty($plans);
        $this->assertEmpty($projects);
        $this->assertEmpty($user);

        $this->assertFalse($this->isAuthenticated(), 'The user is authenticated');
        $res->assertHeader('x-inertia-location', route('landing'));
    }

    /**
     * @test
     */
    public function update_action()
    {
        $this->withSubscribedUser();

        $this->actingAs($this->user);

        $this->put(
            route('user.update', ['user' => $this->user->id]),
            ['username' => 'John Doe']
        );

        $this->user->refresh();

        $this->assertEquals($this->user->username, 'John Doe');
    }
}
