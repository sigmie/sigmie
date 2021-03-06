<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Index\Actions as IndexActions;

trait Testing
{
    use IndexActions, TestConnection;

    protected function setUpSigmieTesting(array $uses)
    {
        if (isset($uses[TestConnection::class])) {
            $this->setupTestConnection();
        }

        if (isset($uses[ClearIndices::class])) {
            $this->clearIndices();
        }

        if (isset($uses[TestIndex::class])) {
            $this->createTestIndex();
        }
    }

    protected function tearDownSigmieTesting(array $uses)
    {
        if (isset($uses[ClearIndices::class])) {
            $this->clearIndices();
        }
    }
}
