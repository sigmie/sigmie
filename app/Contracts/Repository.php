<?php

namespace App\Contracts;

use App\Models\Model;

interface Repository
{
    public function find(int $id): Model;
}
