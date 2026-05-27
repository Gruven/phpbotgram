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
 * to cache the sole instance. The class is otherwise immutable by having
 * no state.
 *
 * **Public constructor (Fix I8 enabler)**: the constructor is `public`
 * (not `private`) so a `new Unspecified()` expression can be used as a
 * default value for typed parameters — PHP 8.1+ allows `new ClassName(...)`
 * in constant expressions, but forbids static method calls there. Code
 * that needs singleton identity (`=== Unspecified::instance()`, e.g. the
 * serializer guards) MUST call `Unspecified::instance()` or normalise a
 * fresh instance to the singleton via `instanceof` first. PollingOptions
 * does exactly that in its constructor body.
 */
final class Unspecified
{
  private static ?self $instance = null;

  public function __construct() {}

  public static function instance(): self
  {
    return self::$instance ??= new self();
  }
}
