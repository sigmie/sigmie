<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectClusterType;
use App\Helpers\ProxyCert;
use App\Http\Controllers\Cluster\TokenController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Sigmie\App\Core\DNS\Contracts\Provider as DNSProvider;
use Sigmie\App\Core\DNS\Records\ARecord;
use Sigmie\Base\APIs\Calls\Cluster as ClusterAPI;
use Sigmie\Base\Http\Connection;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Http\Auth\BasicAuth;
use Sigmie\Http\JSONClient;

class Cluster extends Model
{
    use SoftDeletes;
    use HasApiTokens;
    use HasFactory;
    use ClusterAPI;
    use IndexActions;

    public const QUEUED_DESTROY = 'queued_destroy';

    public const QUEUED_CREATE = 'queued_create';

    public const CREATED = 'created';

    public const RUNNING = 'running';

    public const DESTROYED = 'destroyed';

    public const FAILED = 'failed';

    protected $attributes = [];

    protected $casts = [
        'admin_token_active' => 'boolean',
        'search_token_active' => 'boolean',
    ];

    public function allowedIps()
    {
        return $this->hasMany(AllowedIp::class);
    }

    public function getHasAllowedIpsAttribute()
    {
        return true;
    }

    public function getCanBeDestroyedAttribute()
    {
        return true;
    }

    public function settingsData()
    {
        $data = $this->only(['id', 'state', 'allowedIps', 'has_allowed_ips', 'can_be_destroyed']);

        $data['type'] = $this->getMorphClass();

        return $data;
    }

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

    /**
     * Create assemble new Cluster Connection
     */
    public function newHttpConnection(): Connection
    {
        $port = 8066;
        $domain = config('services.cloudflare.domain');
        $url = "https://proxy.{$this->name}.{$domain}:{$port}";
        $client = JSONClient::create($url, new ProxyCert);

        return new Connection($client);
    }

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

    public function region()
    {
        return $this->belongsTo(Region::class);
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
}
