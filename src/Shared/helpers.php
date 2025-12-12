<?php

declare(strict_types=1);

namespace Sigmie\Functions {

    use Exception;

    function random_name(string $name): string
    {
        return strtolower(prefix_id($name, 5));
    }

    function name_configs(array $values): array
    {
        if (count($values) > 1) {
            throw new Exception('Too many values in name configs');
        }

        [$name] = array_keys($values);
        [$configs] = array_values($values);

        return [$name, $configs];
    }
}
