<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\F;

use Gruven\PhpBotGram\Filters\Filter;

/**
 * Typed F-DSL wrapper for string-valued Telegram fields (e.g.
 * `Message::$text`, `User::$firstName`). Exposes string-specific
 * predicates that bottom out in the underlying `MagicFilter` chain.
 *
 * Each terminal method (`equals`, `contains`, `startsWith`, …) builds a
 * chain operation and bridges the result to a dispatcher-consumable
 * `Filter`. The two transform methods (`lower`, `upper`) and the length
 * projection (`len`) return new field instances so the caller can keep
 * composing typed comparators on top.
 *
 * Mirrors the design spec § "Magic-filter runtime + F-DSL" — Layer 3
 * StringField sketch, minus the codegen-only conveniences (which live on
 * the per-event builders, not the runtime primitive).
 */
final class StringField extends BaseField
{
  /**
   * Exact-match comparator. `F->text->equals('cancel')` accepts a
   * message whose text is exactly `'cancel'`.
   */
  public function equals(string $value): Filter
  {
    return $this->chain->equals($value)->asFilter();
  }

  /**
   * Substring-containment check. Delegates to MagicFilter's `contains`
   * op which uses `str_contains` for string subjects.
   */
  public function contains(string $needle): Filter
  {
    return $this->chain->contains($needle)->asFilter();
  }

  /** Prefix match — delegates to `str_starts_with`. */
  public function startsWith(string $prefix): Filter
  {
    return $this->chain->startsWith($prefix)->asFilter();
  }

  /** Suffix match — delegates to `str_ends_with`. */
  public function endsWith(string $suffix): Filter
  {
    return $this->chain->endsWith($suffix)->asFilter();
  }

  /**
   * Set-membership check: accept when the running string is `==`-equal to
   * any value in `$values`. The list is captured by value at chain-build
   * time so later mutations don't affect the filter.
   *
   * Method name avoids the leading-underscore convention used on
   * `MagicFilter::in_()`; the underscore is necessary on the chain method
   * because `in` is a PHP keyword in some contexts, but here we're at a
   * method-name boundary and `in` is unambiguous.
   *
   * @param list<string> $values
   */
  public function in(array $values): Filter
  {
    return $this->chain->in_($values)->asFilter();
  }

  /**
   * Length projection: extends the chain with a `strlen`/`count`
   * transform and surfaces the result as an `IntField` so callers can
   * stack int comparators (`F->text->len()->gt(5)`).
   */
  public function len(): IntField
  {
    return new IntField($this->chain->len());
  }

  /**
   * UTF-8 aware lowercase transform. Returns a fresh `StringField` so
   * subsequent string comparators see the lowercased running value —
   * enabling `F->text->lower()->equals('cancel')` to accept any case.
   */
  public function lower(): StringField
  {
    return new StringField($this->chain->lower());
  }

  /** UTF-8 aware uppercase transform. Mirrors `lower()`. */
  public function upper(): StringField
  {
    return new StringField($this->chain->upper());
  }
}
