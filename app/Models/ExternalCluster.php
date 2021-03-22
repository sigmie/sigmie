<?php

namespace App\Models;

use App\Models\Model;
use App\Enums\ProjectClusterType;
use App\Helpers\ProxyCert;
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


class ExternalCluster extends Cluster
{
    /**
     * Boot the soft deleting trait for a model.
     *
     * @return void
     */
    public static function bootSoftDeletes()
    {
        // static::addGlobalScope(new SoftDeletingScope);
    }

    /**
     * Create assemble new Cluster Connection
     */
    public function newHttpConnection(): Connection
    {
        $url = $this->url;
        $client = JSONClient::create($url);

        return new Connection($client);
    }
}
