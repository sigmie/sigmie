<?php

namespace Tests\Helpers;

use App\Models\Cluster;
use App\Models\Model;
use App\Models\Project;
use App\Nova\User;
use Illuminate\Notifications\Notifiable;
use PHPUnit\Framework\MockObject\MockObject;

trait NeedsModel
{
    private $modelMock = null;

    /**
     * @return MockObject|Project|Cluster|User
     */
    public function model($model = null)
    {
        if ($model === null) {
            $model = Model::class;
        }

        $methods = [
            'withTrashed',
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
