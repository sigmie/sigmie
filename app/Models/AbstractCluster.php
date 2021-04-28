<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Controllers\Cluster\TokenController;
use Laravel\Sanctum\HasApiTokens;
use Sigmie\App\Core\DNS\Contracts\Provider as DNSProvider;
use Sigmie\App\Core\DNS\Records\ARecord;
use Sigmie\Base\APIs\Calls\Cluster as ClusterAPI;
use Sigmie\Base\Http\Connection;
use Sigmie\Base\Index\Actions as IndexActions;

abstract class AbstractCluster extends Model
{
    use HasApiTokens;
    use ClusterAPI;
    use IndexActions;

    protected $casts = [
        'admin_token_active' => 'boolean',
        'search_token_active' => 'boolean',
    ];
    abstract public function getHasAllowedIpsAttribute();

    abstract public function getCanBeDestroyedAttribute();

    abstract public function settingsData();

    public function tokensArray()
    {
        $plainTextTokens = [TokenController::ADMIN => null, TokenController::SEARCH_ONLY => null];

        if ($this->getAttribute('tokens')->isEmpty()) {
            $plainTextTokens[TokenController::ADMIN] = $this->createToken(TokenController::ADMIN, ['*'])->plainTextToken;
            $plainTextTokens[TokenController::SEARCH_ONLY] = $this->createToken(TokenController::SEARCH_ONLY, ['search'])->plainTextToken;

            $this->refresh();
        }

        $tokens = [];

        foreach ($this->getAttribute('tokens') as $token) {
            $token = $token->only(['name', 'last_used_at', 'created_at', 'id']);

            $token['cluster_id'] = $this->id;

            if ($token['name'] === TokenController::ADMIN) {
                $token['active'] = $this->getAttribute('admin_token_active');
                $token['value'] = $plainTextTokens[TokenController::ADMIN];
            }

            if ($token['name'] === TokenController::SEARCH_ONLY) {
                $token['active'] = $this->getAttribute('search_token_active');
                $token['value'] = $plainTextTokens[TokenController::SEARCH_ONLY];
            }

            $tokens[] = $token;
        }

        return $tokens;
    }

    abstract public function newHttpConnection(): Connection;

    public function removeDNSRecord(): void
    {
        /** @var DNSProvider */
        $dnsProvider = app(DNSProvider::class);

        $dnsProvider->removeRecord(new ARecord($this->name));
    }

    public function health(): array
    {
        $this->setHttpConnection($this->newHttpConnection());

        return $this->clusterAPICall('/health')->json();
    }

    public function indices()
    {
        $this->setHttpConnection($this->newHttpConnection());

        return $this->listIndices();
    }

    public function plans()
    {
        return $this->morphMany(IndexingPlan::class, 'cluster');
    }

    public function isAdminTokenActive(): bool
    {
        return $this->getAttribute('admin_token_active');
    }

    public function isSearchTokenActive(): bool
    {
        return $this->getAttribute('search_token_active');
    }

    public function tokens()
    {
        return $this->morphMany(Token::class, 'tokenable');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function findUser()
    {
        return $this->getAttribute('project')->getAttribute('user');
    }

    public function isOwnedBy(User $user)
    {
        return $this->getAttribute('project')->user->id === $user->id;
    }

    public function aliases()
    {
        $this->setHttpConnection($this->newHttpConnection());

        $catIndexResponse = $this->catAPICall('/indices', 'GET');
        $catAliasResponse = $this->catAPICall('/aliases', 'GET');

        $aliases = collect($catAliasResponse->json())
            ->mapToDictionary(
                fn ($data) => [$data['index'] => $data['alias']]
            );

        $indices = collect($catIndexResponse->json())
            ->map(fn ($values) => [
                'aliases' => (isset($aliases[$values['index']])) ? $aliases[$values['index']] : [],
                'name' => $values['index'],
                'size' => $values['store.size'],
                'docsCount' => $values['docs.count']
            ])->toArray();

        return $indices;
    }
}
