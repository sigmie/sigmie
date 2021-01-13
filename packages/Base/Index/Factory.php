<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Contracts\Connection;
use Sigmie\Base\Contracts\Factory as FactoryInterface;

class Factory implements FactoryInterface
{
    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function connection(Connection $connection): FactoryInterface
    {
        $this->connection = $connection;

        return $this;
    }

    public function create()
    {
    }
}
