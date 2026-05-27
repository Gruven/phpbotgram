<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;

/**
 * Internal helper for operations that may accept either a literal value
 * or a nested `MagicFilter` chain on the right-hand side
 * (`F->id == F->reply->fromUser->id`).
 *
 * Direct port of upstream `magic_filter.helper.resolve_if_needed`
 * (`magic_filter/helper.py`). The upstream function is a free function;
 * we expose it as a static method to satisfy PHP namespacing.
 */
final class Helper
{
  /**
   * If `$value` is itself a `MagicFilter`, resolve it against the chain's
   * original subject and return the resolved value; otherwise pass the
   * literal through unchanged.
   */
  public static function resolveIfNeeded(mixed $value, mixed $initialValue): mixed
  {
    if ($value instanceof MagicFilter) {
      return $value->resolve($initialValue);
    }

    return $value;
  }

  private function __construct() {}
}
