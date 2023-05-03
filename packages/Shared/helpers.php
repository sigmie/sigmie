<?php

declare(strict_types=1);

namespace Sigmie\Functions {
    use Carbon\Carbon;
    use Exception;
    use GuzzleHttp\Promise\Utils;

    function await(array $promises)
    {
        return Utils::settle(
            Utils::unwrap($promises)
        )->wait();
    }

    function index_name(string $prefix): string
    {
        $timestamp = Carbon::now()->format('YmdHisu');

        return "{$prefix}_{$timestamp}";
    }

    function auto_fuzziness(int $oneTypoChars = 3, int $twoTypoChars = 6): string
    {
        return "AUTO:{$oneTypoChars},{$twoTypoChars}";
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

    function random_letters(int $count = 3): string
    {
        $result = [];

        for ($x = 1; $x <= $count; $x++) {
            $result[] = chr(mt_rand(97, 122));
        }

        return implode('', $result);
    }
}
