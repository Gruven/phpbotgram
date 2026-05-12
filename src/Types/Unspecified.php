<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

/**
 * Sentinel singleton for "argument was not provided" cases.
 *
 * Renamed from upstream `UNSET` because PHP reserves `unset` as a keyword
 * so `class Unset` won't parse. The serializer strips fields whose value
 * is Unspecified::instance() before validation/encoding.
 *
 * NOT declared `readonly class`: PHP forbids `static` properties on a
 * readonly class, and the singleton needs `private static ?self $instance`
 * to cache the sole instance. The private constructor + singleton pattern
 * already enforces the desired immutability.
 */
final class Unspecified
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }
}
