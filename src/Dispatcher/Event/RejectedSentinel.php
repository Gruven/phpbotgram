<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

/**
 * Sentinel singleton signalling that a handler explicitly rejected the update
 * (filter chain produced false / handler raised SkipHandlerException). Identity
 * only — `===` is the only reliable comparison.
 */
final class RejectedSentinel
{
  private static ?self $instance = null;

  private function __construct() {}

  public static function instance(): self
  {
    return self::$instance ??= new self();
  }
}
