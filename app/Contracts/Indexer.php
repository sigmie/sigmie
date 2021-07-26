<?php declare(strict_types=1);

namespace App\Contracts;

interface Indexer
{
    public function index();

    public function onFailure();
}
