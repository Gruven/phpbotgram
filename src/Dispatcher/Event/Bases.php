<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

/**
 * Dispatcher base sentinels and helpers. `UNHANDLED`/`REJECTED` are singleton
 * objects (not strings) so identity comparison via `===` can't collide with a
 * user handler that happens to return the same string literal.
 */
final class Bases
{
  public static function unhandled(): UnhandledSentinel
  {
    return UnhandledSentinel::instance();
  }

  public static function rejected(): RejectedSentinel
  {
    return RejectedSentinel::instance();
  }

  public static function skip(?string $message = null): never
  {
    throw new SkipHandlerException($message ?? 'Handler skipped');
  }
}
