<?php

declare(strict_types=1);

namespace Sigmie\AI;

enum Role: string
{
    case System = 'system';

    case User = 'user';

    case Model = 'model';

    public function toOpenAI(): string
    {
        return match ($this) {
            Role::System     => 'system',
            Role::User       => 'user',
            Role::Model  => 'assistant',
        };
    }
}
