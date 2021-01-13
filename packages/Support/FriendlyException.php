<?php

declare(strict_types=1);

namespace Sigmie\Support;

use Exception;
use Sigmie\Contracts\FriendlyException as FriendlyExceptionInterface;

class FriendlyException extends Exception implements FriendlyExceptionInterface
{
}
