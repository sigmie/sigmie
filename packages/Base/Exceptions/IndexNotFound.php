<?php

declare(strict_types=1);

namespace Sigmie\Base\Exceptions;

use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Http\ElasticsearchResponse;

class IndexNotFound extends ElasticsearchException
{
}
