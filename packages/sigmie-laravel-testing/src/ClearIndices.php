<?php

declare(strict_types=1);

namespace Sigmie\Testing\Laravel;

trait ClearIndices
{
    public function clearIndices()
    {
        $helper = $this->app->make(TestingHelper::class);

        $helper->clearIndices();
    }
}
