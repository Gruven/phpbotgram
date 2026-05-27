<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\F;

use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;

/**
 * Abstract base for the typed F-DSL field wrappers. Each subclass binds a
 * `MagicFilter` chain to a narrowly-typed comparator surface — `StringField`
 * exposes string predicates, `IntField` exposes numeric ones, and so on —
 * so call sites get IDE autocomplete and PHPStan-checked parameters without
 * users having to drop into the raw chain API.
 *
 * The runtime primitives in this directory are HAND-written. The per-event
 * typed builders (`MessageF::text()`, `CallbackQueryF::data()`, …) are
 * code-generated on top of these primitives in a later phase (Task 4.12+).
 *
 * Mirrors the design spec § "Magic-filter runtime + F-DSL". The base only
 * owns the `$chain` handle (exposed as `public readonly` for codegen and
 * test introspection) and the `asFilter()` shortcut that bridges the chain
 * to a dispatcher-consumable `Filter`. Concrete subclasses layer typed
 * comparators on top.
 */
abstract class BaseField
{
  /**
   * Hold the chain handle directly: codegen passes a freshly-rooted chain
   * (e.g. `MagicFilter::root()->text`) and subclass methods clone-and-
   * extend it via the chain's immutable append semantics.
   */
  public function __construct(public readonly MagicFilter $chain) {}

  /**
   * Bridge the wrapped chain to a `Filter`. Used by callers that want the
   * raw chain verdict without going through a typed comparator — e.g. an
   * existence check on a nullable-typed field where the underlying
   * `MagicFilter::asFilter()` reject-on-null behaviour is exactly what
   * the user wants.
   *
   * Returns a `MagicFilterAsFilter` instance under the hood; see
   * `MagicFilterAsFilter` for the bool|array acceptance contract.
   */
  public function asFilter(): Filter
  {
    return $this->chain->asFilter();
  }
}
