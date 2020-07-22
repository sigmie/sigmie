<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use App\Models\Project;
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
        $tokens = [];
        $plainTextTokens = [self::ADMIN => null, self::SEARCH_ONLY => null];

        if ($cluster->tokens->isEmpty()) {

            $plainTextTokens[self::ADMIN] = $cluster->createToken(self::ADMIN, ['*'])->plainTextToken;
            $plainTextTokens[self::SEARCH_ONLY] = $cluster->createToken(self::SEARCH_ONLY, ['search'])->plainTextToken;

            $cluster->refresh();
        }

        foreach ($cluster->tokens as $token) {
            $token = $token->only(['name', 'last_used_at', 'created_at', 'id']);

            $token['cluster_id'] = $cluster->getAttribute('id');

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

    public function toogle(Cluster $cluster, int $tokenId)
    {
        $token = $cluster->tokens()->where('id', $tokenId)->get()->first();
        $oldValue = null;

        if ($token->getAttribute('name') === self::SEARCH_ONLY) {
            $oldValue = $cluster->getAttribute('search_token_active');
            $cluster->update(['search_token_active' => !$oldValue]);
            $newValue = $cluster->getAttribute('search_token_active');
        }

        if ($token->getAttribute('name') === self::ADMIN) {
            $oldValue = $cluster->getAttribute('admin_token_active');
            $cluster->update(['admin_token_active' => !$oldValue]);
            $newValue = $cluster->getAttribute('admin_token_active');
        }

        return $newValue;
    }

    public function regenerate(Cluster $cluster, int $tokenId)
    {
        $oldToken = $cluster->tokens()->where('id', $tokenId)->get()->first();

        $newToken = $cluster->createToken($oldToken->getAttribute('name'), $oldToken->getAttribute('abilities'));

        $oldToken->delete();

        return ['value' => $newToken->plainTextToken, 'id' => $newToken->accessToken->getAttribute('id')];
    }
}
