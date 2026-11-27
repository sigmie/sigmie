<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use Sigmie\Base\Contracts\ElasticsearchResponse;

trait Tasks
{
    use Index;

    public function tasksAPICall(): ElasticsearchResponse
    {
        return $this->indexAPICall('_tasks', 'GET');
    }
}
