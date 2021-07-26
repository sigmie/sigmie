<?php

namespace Sigmie\Testing\Laravel;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Assert as PHPUnit;

class Assertions
{
    public function assertSigmie()
    {
        // return function ($component = null, $props = []) {
        //     $this->assertViewHas('page');

        //     tap($this->viewData('page'), function ($view) {
        //         PHPUnit::assertArrayHasKey('component', $view);
        //         PHPUnit::assertArrayHasKey('props', $view);
        //         PHPUnit::assertArrayHasKey('url', $view);
        //         PHPUnit::assertArrayHasKey('version', $view);
        //     });

        //     if (! is_null($component)) {
        //         PHPUnit::assertEquals($component, $this->viewData('page')['component']);
        //     }

        //     $this->assertInertiaHasAll($props);

        //     return $this;
        // };
    }

}
