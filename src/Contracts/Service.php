<?php

declare(strict_types=1);

namespace Sigmie\Contracts;

use Illuminate\Support\Collection;
use Sigmie\Contracts\Entity;

interface Service
{
    public function create($data);

    public function delete($identifier);

    public function list();

    public function get($identifier);
}
