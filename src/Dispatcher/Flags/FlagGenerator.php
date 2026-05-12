<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Flags;

/**
 * Sugary factory mirror of upstream's module-level `from aiogram import flags`
 * singleton. The Python original supports `flags.admin_only` (attribute access
 * with no parens), `flags.throttle(5)` (callable returning a decorator with a
 * value), and `flags.chat_action(action='typing')` (keyword form). PHP can't
 * make attribute access return a configurable value, so we collapse all three
 * into a single magic-static call form: `FlagGenerator::<name>(?$value)`.
 *
 * Usage:
 *   FlagGenerator::admin_only();        // Flag('admin_only', true)
 *   FlagGenerator::throttle(5);         // Flag('throttle', 5)
 *   FlagGenerator::chat_action('typing'); // Flag('chat_action', 'typing')
 *
 * Statically-typed call sites that lose autocompletion through `__callStatic`
 * can use the explicit `flag()` factory instead — same behaviour, IDE-friendly.
 * Call sites that *must* use the magic form should refer to `__callStatic`
 * directly (the return type and arg shape are documented there); PHPStan does
 * not synthesise per-name `@method` tags from a `__callStatic` signature alone.
 */
final class FlagGenerator
{
  /**
   * Magic-static call form: `FlagGenerator::<name>(?$value)`. The method name
   * becomes the flag name; the first argument (or `true` when omitted)
   * becomes the value.
   *
   * Trailing arguments are ignored — upstream's generator only consumes one
   * positional value, and silently dropping the rest keeps the surface
   * area aligned.
   *
   * @param array<int, mixed> $args
   */
  public static function __callStatic(string $name, array $args): Flag
  {
    return new Flag($name, $args[0] ?? true);
  }

  /**
   * Explicit factory. Equivalent to `FlagGenerator::<$name>($value)` but
   * statically discoverable — call sites that need IDE autocompletion or
   * compile-time symbol lookup should prefer this over the magic form.
   */
  public static function flag(string $name, mixed $value = true): Flag
  {
    return new Flag($name, $value);
  }
}
