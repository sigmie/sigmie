<?php

declare(strict_types=1);

namespace Sigmie\Enums;

use Sigmie\Support\VectorMath;

enum VectorStrategy: string
{
    // Low accuracy
    // Good for short strings
    case Concatenate = 'concatenate';

    // Average accuracy
    // Good for long strings
    case Average = 'average';

    // Max accuracy
    // Good for short strings
    case ScriptScore = 'script_score';

    public function suffix(): string
    {
        return match ($this) {
            self::Concatenate => 'concat',
            self::Average => 'avg',
            self::ScriptScore => 'script',
        };
    }

    public function prepare(array $values): array
    {
        return match ($this) {
            self::Concatenate => [implode(' ', $values)],
            default => $values,
        };
    }

    public function format(array $embeddings, bool $normalize = true): array
    {
        $embeddings = array_map('array_values', $embeddings);

        if ($this === self::ScriptScore) {
            return array_map(fn ($embedding): array => [
                'vector' => $normalize ? VectorMath::normalize($embedding) : $embedding,
            ], array_values($embeddings));
        }

        $vector = $this === self::Average && count($embeddings) > 1
            ? VectorMath::average($embeddings)
            : (reset($embeddings) ?: []);

        return $normalize ? VectorMath::normalize($vector) : $vector;
    }
}
