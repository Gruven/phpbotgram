<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Flags;

use WeakMap;

/**
 * Imperative attachment seam for flags. Mirrors the runtime half of
 * `aiogram.dispatcher.flags.FlagDecorator.__call__` — the branch that mutates
 * the callback by setting `value.aiogram_flag = {...}`.
 *
 * Python can stamp attributes directly onto a function object. PHP cannot —
 * `\Closure` instances reject ad-hoc property assignment. We instead key a
 * process-wide `WeakMap<\Closure|object, list<Flag>>` by the target. When the
 * target is garbage-collected, its entry evicts automatically — no manual
 * cleanup needed even when the dispatcher rebuilds its handler graph.
 *
 * Note that `WeakMap` only accepts objects; that's why the signature is
 * `\Closure|object` rather than `callable`. Callers with a string-callable or
 * array-callable should lift it via `\Closure::fromCallable($cb)` first.
 *
 * The class is process-static by design — there's only one logical map of
 * "flag attachments" per process. The `reset()` seam exists exclusively for
 * test isolation; production code never calls it.
 */
final class FlagDecorator
{
  /**
   * Lazily-initialised on first access. Static null-coalesce assignment in
   * `storage()` keeps the bootstrap branch small and avoids a constructor.
   *
   * @var null|WeakMap<object, list<Flag>>
   */
  private static ?WeakMap $storage = null;

  /**
   * Attach a flag to a closure or object. The target is returned unchanged so
   * registration sites can chain: `$cb = FlagDecorator::attach($cb, new Flag(...));`.
   *
   * Multiple attaches accumulate; storage is keyed by object identity, so two
   * different closures (even with identical source) get independent flag
   * lists. The `object` parameter type accepts `\Closure` since closures are
   * objects — a redundant `\Closure|object` union is rejected by PHP 8.5.
   */
  public static function attach(object $target, Flag $flag): object
  {
    $store = self::storage();
    $existing = $store[$target] ?? [];
    $existing[] = $flag;
    $store[$target] = $existing;

    return $target;
  }

  /**
   * Flags attached to `$target` via `attach()`, in attachment order. Does
   * NOT include attribute-driven flags — for the combined view use
   * `Flags::extractFlags()`.
   *
   * @return list<Flag>
   */
  public static function attached(object $target): array
  {
    return self::storage()[$target] ?? [];
  }

  /**
   * Drop every attachment. Intended for test setUp/tearDown — never call
   * from production code, as it nukes flags attached by any other code in
   * the same process.
   *
   * @internal
   */
  public static function reset(): void
  {
    self::$storage = new WeakMap();
  }

  /**
   * Lazy accessor for the shared WeakMap. The `??=` operator initialises on
   * first read and short-circuits afterwards, matching the singleton pattern
   * upstream uses for module-level dicts.
   *
   * @return WeakMap<object, list<Flag>>
   */
  private static function storage(): WeakMap
  {
    return self::$storage ??= new WeakMap();
  }
}
