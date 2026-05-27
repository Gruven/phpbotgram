<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

use Attribute;

/**
 * Class-level metadata carrier for `CallbackData` subclasses. Declares the
 * wire prefix and separator that the framework uses to pack/unpack a typed
 * callback-data payload.
 *
 *   #[CallbackPrefix('order', sep: ':')]
 *   final class OrderCallback extends CallbackData { ... }
 *
 * Mirrors upstream's `class OrderCallback(CallbackData, prefix='order',
 * sep=':')` keyword-argument-on-subclass syntax (`aiogram/filters/
 * callback_data.py:50-65`). PHP has no equivalent of `__init_subclass__`
 * for capturing class-declaration keywords, so we substitute a class-level
 * attribute. `CallbackData::reflectMeta` reads it once per subclass and
 * caches nothing (PHP's reflection cache makes the per-call lookup cheap;
 * adding a memo map would add complexity for negligible win).
 *
 * Constraints:
 *   - Targets classes only (not methods/properties) — pinned by the
 *     `Attribute::TARGET_CLASS` flag and verified in
 *     `CallbackPrefixTest::testAttributeIsClassTargeted`.
 *   - Not repeatable: each subclass declares exactly one
 *     `#[CallbackPrefix]`. The `getAttributes()` lookup picks the first
 *     entry but PHP itself rejects stacking when `IS_REPEATABLE` is
 *     omitted from the flag set, so the invariant is engine-enforced.
 *   - The base validates that `$sep` is not contained in `$prefix` lazily,
 *     inside `CallbackData::reflectMeta`. Doing the check inside the
 *     attribute constructor would require throwing during attribute
 *     instantiation, which couples failure mode to reflection timing.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CallbackPrefix
{
  /**
   * @param non-empty-string $sep Separator between encoded fields. Pinned
   *                              as `non-empty-string` so `explode($sep, $wire)` in `CallbackData::
   *                              unpack` doesn't trip PHPStan's `argument.type` check at level 9. The
   *                              runtime equivalent of this constraint is enforced inside
   *                              `CallbackData::reflectMeta`, which raises `LogicException` when the
   *                              separator is empty — but the type system carries the same guarantee
   *                              statically.
   */
  public function __construct(
    public string $prefix,
    public string $sep = ':',
  ) {}
}
