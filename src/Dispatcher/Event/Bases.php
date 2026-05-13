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

  /**
   * Stop dispatch entirely — the observer collapses the in-flight handler
   * iteration to `RejectedSentinel`. Used by handlers that want to assert
   * "this update has been handled by someone else, do not try further
   * handlers on this observer or fall through to other routers".
   *
   * Mirrors aiogram's `CancelHandler` exception path; the upstream observer
   * does not have a dedicated `cancel()` helper but `Bases::skip()` /
   * `Bases::cancel()` give parity with the broader handling protocol.
   */
  public static function cancel(?string $message = null): never
  {
    throw new CancelHandlerException($message ?? 'Handler cancelled');
  }
}
