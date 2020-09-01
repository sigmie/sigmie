<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Cluster;
use App\Models\Model;
use App\Models\NewsletterSubscription;
use App\Models\Project;
use App\Models\User;
use PHPUnit\Framework\MockObject\MockObject;

trait NeedsModel
{
    private $modelMock = null;

    /**
     * @return MockObject|Project|Cluster|User|NewsletterSubscription
     */
    public function model($model = null)
    {
        if ($model === null) {
            $model = Model::class;
        }

        $methods = [
            'withTrashed',
            'firstOrCreate',
            'where',
            'first',
            'create',
            'delete',
            'getAttribute',
            'find',
            'all',
            'save',
            'update',
            'restore',
            'firstWhere'
        ];

        if ($this->modelMock === null) {
            $this->modelMock = $this->getMockBuilder($model)->setMethods($methods)->getMock();
        }

        return $this->modelMock;
    }
}
