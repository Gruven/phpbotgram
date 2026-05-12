<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use JsonSerializable;
use LogicException;

/**
 * Sentinel for "use the bot's configured default for this field".
 *
 * Renamed from upstream `Default` because PHP reserves `default` as a keyword
 * (case-insensitive) so `class Default` won't parse even when namespaced.
 *
 * The Serializer always resolves BotDefault instances against
 * $bot->getDefaultProperties() before encoding. jsonSerialize throws so a
 * BotDefault that escapes resolution fails loudly rather than silently
 * emitting `null` on the wire.
 */
final class BotDefault implements JsonSerializable
{
  /**
   * Per-name singleton cache so generated method defaults share identity.
   * Lives on the class rather than as a `readonly` property because PHP 8.5
   * forbids static properties on `readonly` classes; the public `$name` is
   * still promoted-readonly below.
   *
   * @var array<string, self>
   */
  private static array $cache = [];

  public function __construct(public readonly string $name) {}

  /**
   * Returns a per-name singleton so `BotDefault::for('parse_mode') === BotDefault::for('parse_mode')`.
   *
   * NOTE: PHP 8.5 disallows static method calls in default-parameter expressions
   * (only `new ClassName(...)` is legal there), so Phase 2 codegen still has to
   * emit `new BotDefault(...)` for default parameter values. This helper is for
   * non-default callsites — user code that wants a stable sentinel identity when
   * comparing externally.
   */
  public static function for(string $name): self
  {
    return self::$cache[$name] ??= new self($name);
  }

  public function equals(BotDefault $other): bool
  {
    return $this->name === $other->name;
  }

  public function jsonSerialize(): never
  {
    throw new LogicException(
      "BotDefault sentinel reached json_encode without being resolved: {$this->name}"
    );
  }

  public function __toString(): string
  {
    return "BotDefault('{$this->name}')";
  }
}
