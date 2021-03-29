<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectClusterType;
use App\Helpers\ProxyCert;
use App\Http\Controllers\Cluster\TokenController;
use App\Jobs\Cluster\UpdateClusterAllowedIps;
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

class Cluster extends AbstractCluster
{
    use SoftDeletes;
    use HasApiTokens;
    use HasFactory;
    use ClusterAPI;
    use IndexActions;

    public const QUEUED_DESTROY = 'queued_destroy';

    public const QUEUED_CREATE = 'queued_create';

    public const QUEUED_UPDATE = 'queued_update';

    public const UPDATING = 'updating';

    public const CREATED = 'created';

    public const RUNNING = 'running';

    public const DESTROYED = 'destroyed';

    public const FAILED = 'failed';

    protected $casts = [
        'admin_token_active' => 'boolean',
        'search_token_active' => 'boolean',
        'design' => 'array'
    ];

    public function allowedIps()
    {
        return $this->hasMany(AllowedIp::class);
    }

    public function getHasBasicAuthAttribute()
    {
        return true;
    }

    public function getHasAllowedIpsAttribute()
    {
        return true;
    }

    public function getCanBeDestroyedAttribute()
    {
        return true;
    }

    public function dispatchUpdateAllowedIps(): void
    {
        $this->update(['state' => Cluster::QUEUED_UPDATE]);
        UpdateClusterAllowedIps::dispatch($this->id);
    }

    public function settingsData()
    {
        $data = $this->only([
            'id',
            'state',
            'username', 'allowedIps', 'has_allowed_ips', 'can_be_destroyed',
            'has_basic_auth'
        ]);

        $data['type'] = $this->getMorphClass();

        return $data;
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

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
