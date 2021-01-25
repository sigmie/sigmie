<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
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

    protected $casts = [
        'admin_token_active' => 'boolean',
        'search_token_active' => 'boolean'
    ];

    /**
     * Create assemble new Cluster Connection
     */
    public function newHttpConnection(): Connection
    {
        $auth = new BasicAuth($this->username, decrypt($this->password));
        $client = JSONClient::create($this->url, $auth);

        return new Connection($client);
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
        return $this->hasMany(IndexingPlan::class);
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
