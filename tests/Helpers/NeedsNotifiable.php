<?php declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\User;
use Illuminate\Notifications\Notifiable;
use PHPUnit\Framework\MockObject\MockObject;

trait NeedsNotifiable
{
    /**
     * @return MockObject|User
     */
    public function notifiable()
    {
        $methods = [
            'getKey', 'notify'
        ];

        return $this->getMockBuilder(Notifiable::class)->setMethods($methods)->getMockForTrait();
    }
}
