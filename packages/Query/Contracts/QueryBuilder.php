<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface QueryBuilder
{
    public function get();

    public function limit($limit);

    public function paginate($perPage);
}
