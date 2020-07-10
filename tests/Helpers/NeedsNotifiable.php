<?php

namespace Tests\Helpers;

use Illuminate\Notifications\Notifiable;
use PHPUnit\Framework\MockObject\MockObject;

trait NeedsNotifiable
{
    /**
     * @return MockObject
     */
    public function notifiable()
    {
        $methods = [
            'getKey', 'notify'
        ];

        return $this->getMockBuilder(Notifiable::class)->setMethods($methods)->getMockForTrait();
    }
}
