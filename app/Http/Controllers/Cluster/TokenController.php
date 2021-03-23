<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cluster;

use App\Models\Cluster;
use App\Models\Project;
use App\Models\Token;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TokenController extends \App\Http\Controllers\Controller
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
        $cluster = $project->clusters->first();

        $this->authorize('index', [Token::class, $cluster]);

        $tokens = $cluster->tokensArray();

        return Inertia::render('token/index', ['tokens' => $tokens]);
    }

    public function toogle(Project $project, Token $clusterToken)
    {
        $cluster = $project->clusters->first();

        $this->authorize('update', [$clusterToken, $cluster]);

        $newValue = null;

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

        return ['active' => $newValue];
    }

    public function regenerate(Project $project, Token $clusterToken)
    {
        $cluster = $project->clusters->first();

        $this->authorize('update', [$clusterToken, $cluster]);

        $newToken = $cluster->createToken(
            $clusterToken->getAttribute('name'),
            $clusterToken->getAttribute('abilities')
        );

        $clusterToken->delete();

        return [
            'value' => $newToken->plainTextToken,
            'id' => $newToken->accessToken->getAttribute('id')
        ];
    }
}
