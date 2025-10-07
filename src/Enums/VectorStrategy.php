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

    public function format(array $embeddings): array
    {
        return (match ($this) {
            self::Concatenate => function ($embeddings) {
                return $embeddings[0] ?? [];
            },
            self::Average => function ($embeddings) {

                $count = count($embeddings);

                if ($count === 1) {
                    return $embeddings[0];
                }

                $dimensions = count($embeddings[0]);
                $sum = array_fill(0, $dimensions, 0.0);

                foreach ($embeddings as $vector) {
                    foreach ($vector as $i => $val) {
                        $sum[$i] += $val;
                    }
                }

                $averaged = array_map(fn($total) => $total / $count, $sum);

                // Normalize the averaged vector to ensure magnitude = 1.0
                // This is critical for dot_product and max_inner_product similarity
                return VectorMath::normalize($averaged);
            },
            self::ScriptScore => function (array $embeddings) {
                // For ScriptScore: create array of objects with embedding field
                return array_map(function ($embedding) {
                    return ['vector' => $embedding];
                }, $embeddings);
            },
        })($embeddings);
    }
}
