<?php

declare(strict_types=1);

namespace Sigmie\Testing;

trait Testing
{
    use TestConnection;

    protected function setUpSigmieTesting(array $uses): void
    {
        if (isset($uses[TestConnection::class])) {
            $this->setupTestConnection();
        }
    }

    protected function tearDownSigmieTesting(array $uses): void
    {
    }
}
