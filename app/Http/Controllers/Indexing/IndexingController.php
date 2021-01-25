<?php

declare(strict_types=1);

namespace App\Http\Controllers\Indexing;

use Inertia\Inertia;

class IndexingController extends \App\Http\Controllers\Controller
{
    public function __invoke()
    {

        return Inertia::render('indexing/indexing');
    }
}
