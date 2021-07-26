<?php declare(strict_types=1);

namespace App\Models;

use Sigmie\Base\Http\Connection;
use Sigmie\Http\JSONClient;

class ExternalCluster extends AbstractCluster
{
    public function getHasAllowedIpsAttribute()
    {
        return false;
    }

    public function getCanBeDestroyedAttribute()
    {
        return false;
    }

    public function settingsData()
    {
        $data = $this->only(['id', 'state', 'has_allowed_ips', 'can_be_destroyed']);

        $data['type'] = $this->getMorphClass();

        return $data;
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
