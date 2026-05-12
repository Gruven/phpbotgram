<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Flags;

use Closure;
use ReflectionFunction;
use ReflectionObject;

/**
 * Read flags from a target, combining both attachment styles:
 *
 * - **Imperative** — flags attached via `FlagDecorator::attach()`.
 * - **Attribute** — `#[Flag(...)]` decorations on the closure / method / class.
 *
 * Mirrors the read half of `aiogram.dispatcher.flags` (`extract_flags`,
 * `extract_flags_from_object`, `get_flag`, `check_flags`). The two storage
 * paths exist because Python attaches flags by mutating the callback object
 * directly (`value.aiogram_flag = {...}`) — PHP can't do that with closures,
 * so we use a WeakMap + attribute reflection hybrid.
 *
 * **Precedence**: imperative attachments come first in `extractFlags()`, then
 * attribute-driven ones. `getFlag()` therefore prefers imperative attachments
 * when the same name appears twice — useful when the imperative path is being
 * used to override a class-level default.
 */
final class Flags
{
  /**
   * All flags attached to `$target`, imperative attachments first then
   * attribute-driven ones. The `object` parameter type accepts `\Closure`
   * since closures are objects — a redundant `\Closure|object` union is
   * rejected by PHP 8.5.
   *
   * @return list<Flag>
   */
  public static function extractFlags(object $target): array
  {
    $imperative = FlagDecorator::attached($target);
    $attributes = self::extractFromAttributes($target);

    return [...$imperative, ...$attributes];
  }

  /**
   * Class-level `#[Flag]` attributes on the given object. Used when the
   * handler is dispatched against a class rather than a callable — the class
   * itself carries the flag metadata (mirror of upstream's
   * `extract_flags_from_object`).
   *
   * @return list<Flag>
   */
  public static function extractFlagsFromObject(object $obj): array
  {
    $refl = new ReflectionObject($obj);
    $flags = [];

    foreach ($refl->getAttributes(Flag::class) as $attr) {
      $flags[] = $attr->newInstance();
    }

    return $flags;
  }

  /**
   * First flag matching `$name`, or `null` if absent. Imperative attachments
   * win over attribute-driven ones — see class docblock for the precedence
   * rationale.
   */
  public static function getFlag(object $target, string $name): ?Flag
  {
    foreach (self::extractFlags($target) as $flag) {
      if ($flag->name === $name) {
        return $flag;
      }
    }

    return null;
  }

  /**
   * Predicate: every name in `$required` must appear on `$target` (by flag
   * name, regardless of value). An empty `$required` list is vacuously true.
   *
   * Used by middleware-style gates that ask "does this handler opt into all
   * of these capabilities" — e.g. `Flags::checkFlags($h, ['auth', 'paid'])`.
   *
   * @param list<string> $required
   */
  public static function checkFlags(object $target, array $required): bool
  {
    if ($required === []) {
      return true;
    }

    $byName = [];

    foreach (self::extractFlags($target) as $flag) {
      $byName[$flag->name] = true;
    }

    foreach ($required as $name) {
      if (!isset($byName[$name])) {
        return false;
      }
    }

    return true;
  }

  /**
   * Attribute-only path. For closures we go through `ReflectionFunction`;
   * for objects through `ReflectionObject` (which reads class-level
   * attributes). Method-level attributes on an object are NOT collected here
   * — callers that want a specific method's flags should pass
   * `Closure::fromCallable([$obj, 'method'])` instead.
   *
   * @return list<Flag>
   */
  private static function extractFromAttributes(object $target): array
  {
    if ($target instanceof Closure) {
      $refl = new ReflectionFunction($target);
      $attrs = $refl->getAttributes(Flag::class);
    } else {
      $refl = new ReflectionObject($target);
      $attrs = $refl->getAttributes(Flag::class);
    }

    $flags = [];

    foreach ($attrs as $attr) {
      $flags[] = $attr->newInstance();
    }

    return $flags;
  }
}
