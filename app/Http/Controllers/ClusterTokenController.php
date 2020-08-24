<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cluster;
use App\Models\ClusterToken;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ClusterTokenController extends Controller
{
    public const ADMIN = 'Admin';

    public const SEARCH_ONLY = 'Search-Only';

    /**
     * In the plain token value there is also the token
     * id returned which leads to more performance by
     * the authentication. (https://github.com/laravel/sanctum/pull/136)
     */
    public function index(Project $project)
    {
        $cluster = $project->clusters()->first();
        $plainTextTokens = [self::ADMIN => null, self::SEARCH_ONLY => null];

        Gate::authorize('index', [ClusterToken::class, $cluster]);

        if ($cluster->getAttribute('tokens')->isEmpty()) {
            $plainTextTokens[self::ADMIN] = $cluster->createToken(self::ADMIN, ['*'])->plainTextToken;
            $plainTextTokens[self::SEARCH_ONLY] = $cluster->createToken(self::SEARCH_ONLY, ['search'])->plainTextToken;

            $cluster->refresh();
        }

        $clusterId = $cluster->getAttribute('id');

        $tokens = [];
        foreach ($cluster->getAttribute('tokens') as $token) {
            $token = $token->only(['name', 'last_used_at', 'created_at', 'id']);

            $token['cluster_id'] = $clusterId;

            if ($token['name'] === self::ADMIN) {
                $token['active'] = $cluster->getAttribute('admin_token_active');
                $token['value'] = $plainTextTokens[self::ADMIN];
            }

            if ($token['name'] === self::SEARCH_ONLY) {
                $token['active'] = $cluster->getAttribute('search_token_active');
                $token['value'] = $plainTextTokens[self::SEARCH_ONLY];
            }

            $tokens[] = $token;
        }

        return Inertia::render('token/index', ['tokens' => $tokens]);
    }

    public function toogle(Cluster $cluster, ClusterToken $clusterToken)
    {
        Gate::authorize('update', [$clusterToken, $cluster]);

        if ($clusterToken->getAttribute('name') === self::SEARCH_ONLY) {
            $oldValue = $cluster->getAttribute('search_token_active');
            $cluster->update(['search_token_active' => !$oldValue]);
            $newValue = $cluster->getAttribute('search_token_active');
        }

        if ($clusterToken->getAttribute('name') === self::ADMIN) {
            $oldValue = $cluster->getAttribute('admin_token_active');
            $cluster->update(['admin_token_active' => !$oldValue]);
            $newValue = $cluster->getAttribute('admin_token_active');
        }

        return $newValue;
    }

    public function regenerate(Cluster $cluster, ClusterToken $clusterToken)
    {
        Gate::authorize('update', [$clusterToken, $cluster]);

        $newToken = $cluster->createToken($clusterToken->getAttribute('name'), $clusterToken->getAttribute('abilities'));

        $clusterToken->delete();

        return ['value' => $newToken->plainTextToken, 'id' => $newToken->accessToken->getAttribute('id')];
    }
}
