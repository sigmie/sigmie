<?php

namespace Tests\Helpers;

use App\Models\Model;
use Illuminate\Notifications\Notifiable;
use PHPUnit\Framework\MockObject\MockObject;

trait NeedsModel
{
    private $modelMock = null;

    /**
     * @return MockObject|Model
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
            'find',
            'all'
        ];

        if ($this->modelMock === null) {
            $this->modelMock = $this->getMockBuilder($model)->setMethods($methods)->getMock();
        }

        return $this->modelMock;
    }
}
