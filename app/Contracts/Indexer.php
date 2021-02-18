<?php

namespace App\Contracts;

interface Indexer
{
    public function index();

    public function onFailure();
}
