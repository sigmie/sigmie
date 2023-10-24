<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use PHPUnit\Framework\Assert as PHPUnit;

trait Contracts
{
    public static function assertContains($needle, iterable $haystack, string $message): void
    {
        PHPUnit::assertContains($needle, $haystack, $message);
    }

    public static function assertNotContains($needle, iterable $haystack, string $message): void
    {
        PHPUnit::assertNotContains($needle, $haystack, $message);
    }

    public static function assertEquals($expected, $actual, string $message): void
    {
        PHPUnit::assertEquals($expected, $actual, $message);
    }

    public static function assertNotEquals($expected, $actual, string $message): void
    {
        PHPUnit::assertNotEquals($expected, $actual, $message);
    }

    public static function assertArrayHasKey($key, $array, string $message): void
    {
        PHPUnit::assertArrayHasKey($key, $array, $message);
    }

    public static function assertArrayNotHasKey($key, $array, string $message): void
    {
        PHPUnit::assertArrayNotHasKey($key, $array, $message);
    }

    public static function assertEmpty($actual, string $message = ''): void
    {
        PHPUnit::assertEmpty($actual, $message);
    }
}
