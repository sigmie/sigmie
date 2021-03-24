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

class Cluster extends AbstractCluster
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
