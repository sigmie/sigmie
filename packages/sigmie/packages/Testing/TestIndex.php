<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;

trait TestIndex
{
    use TestConnection, IndexActions, ClearIndices;

    private $testIndexName;

    abstract protected function testId(): string;

    public function createTestIndex()
    {
        $this->testIndexName = $this->testId() . '_' . bin2hex(openssl_random_pseudo_bytes(10));

        $this->createIndex(new Index($this->testIndexName));
    }

    public function getTestIndex(): Index
    {
        return $this->getIndex($this->testIndexName);
    }

    protected function index(): Index
    {
        return $this->getTestIndex();
    }
}
