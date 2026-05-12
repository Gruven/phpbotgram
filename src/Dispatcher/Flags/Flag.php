<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Flags;

use Attribute;
use Stringable;

/**
 * Per-handler metadata tag, mirror of upstream `aiogram.dispatcher.flags.Flag`
 * (`@dataclass(frozen=True)`). Two roles:
 *
 * 1. **Value object** — `name`/`value` carried in a list on `HandlerObject`,
 *    read by middleware/filters at dispatch time
 *    (`Flags::getFlag($h, 'chat_action')?->value`).
 * 2. **Repeatable PHP attribute** — stack any number of `#[Flag('name', $value)]`
 *    on a handler method, function, or closure literal; `Flags::extractFlags()`
 *    reads them back via reflection.
 *
 * Booleans are the common case (`#[Flag('admin_only')]`), so `value` defaults
 * to `true` and `__toString()` renders the name alone for grep-friendly log
 * output. Non-boolean values render as `name=value` via `var_export()` so
 * quoting survives the round-trip.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Flag implements Stringable
{
  public function __construct(
    public string $name,
    public mixed $value = true,
  ) {}

  public function __toString(): string
  {
    return $this->value === true
      ? $this->name
      : sprintf('%s=%s', $this->name, var_export($this->value, true));
  }
}
