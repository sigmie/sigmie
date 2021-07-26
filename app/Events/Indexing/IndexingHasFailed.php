<?php

declare(strict_types=1);

namespace App\Events\Indexing;

use App\Exceptions\IndexingException;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IndexingHasFailed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public IndexingException $indexingException)
    {
    }
}
