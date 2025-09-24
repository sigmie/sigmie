<?php

declare(strict_types=1);

namespace Sigmie\Enums;

enum MessageRole: string
{
    case System = 'system';

    case User = 'user';

    case Developer = 'developer';

    case Assistant = 'assistant';
}
