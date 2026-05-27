<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

/**
 * Sentinel singleton returned/compared by reference to mark a handler chain that
 * did not produce a result (mirrors aiogram's `unittest.mock.sentinel.UNHANDLED`).
 * Identity-only — `===` is the only reliable comparison.
 */
final class UnhandledSentinel
{
  private static ?self $instance = null;

  private function __construct() {}

  public static function instance(): self
  {
    return self::$instance ??= new self();
  }
}
