<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Paddle\Billable;
use Laravel\Paddle\Receipt;
use Laravel\Paddle\Subscription;

class User extends Authenticatable
{
    use Notifiable;
    use Billable;
    use HasFactory;

    protected $fillable = [
        'email', 'username', 'password', 'avatar_url', 'github',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function isSubscribed()
    {
        return $this->subscribed(config('services.paddle.plan_name'));
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function deleteUserData()
    {
        $this->projects->each(function (Project $project) {
            // Clean cluster records and it's dns entries
            $project->clusters->each(function (AbstractCluster $cluster) {
                // Delete indexing plans
                $cluster->plans->each(function (IndexingPlan $plan) {
                    // Delete plan activities
                    IndexingActivity::where('plan_id', $plan->id)->delete();

                    $plan->delete();
                });

                //Delete cluster tokens
                $cluster->tokens->each(fn (Token $token) => $token->delete());

                //Remove cluster dns record
                $cluster->removeDNSRecord();

                // Force delete cluster
                $cluster->forceDelete();
            });

            // Delete the project
            $project->delete();
        });

        //Delete subscription and receipts
        $this->subscriptions->each(function (Subscription $subscription) {
            $subscription->receipts->each(function (Receipt $receipt) {
                $receipt->delete();
            });

            $subscription->delete();
        });

        //Remove notifications
        $this->notifications->each(fn ($notification) => $notification->delete());

        //Delete user
        $this->delete();
    }
}
