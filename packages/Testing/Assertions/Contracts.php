<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait Contracts
{
    abstract public static function assertContains($needle, iterable $haystack, string $message = ''): void;

    abstract public static function assertNotContains($needle, iterable $haystack, string $message = ''): void;

    abstract public static function assertEquals($expected, $actual, string $message = ''): void;

    abstract public static function assertNotEquals($expected, $actual, string $message = ''): void;

    abstract public static function assertArrayHasKey($key, $array, string $message = ''): void;

    abstract public static function assertArrayNotHasKey($key, $array, string $message = ''): void;

    abstract protected function indexData(string $name): array;
}
