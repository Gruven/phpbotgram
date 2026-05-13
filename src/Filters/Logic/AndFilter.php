<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\Logic;

use Gruven\PhpBotGram\Filters\Filter;

/**
 * "Every child must accept" combinator with a kwarg cascade.
 *
 * This class is named after upstream `aiogram.filters.logic._AndFilter`
 * (`aiogram/filters/logic.py`) but **intentionally widens** that class's
 * `__call__` semantics in one respect: the kwarg cascade.
 *
 * # Upstream `_AndFilter.__call__` (no cascade)
 *
 * Upstream calls each target as `await target.call(*args, **kwargs)` where
 * `kwargs` is the **original** set received by `__call__` — it does NOT spread
 * any dict results from earlier siblings into the next call.  The accumulated
 * `final_result` dict is only returned at the *end*, not forwarded mid-loop.
 *
 * # PHP widening: intentional kwarg cascade
 *
 * The PHP port instead passes `[...$kwargs, ...$merged]` to every child
 * (line 48), so each filter sees the original kwargs *plus* every kwarg
 * contributed by any preceding sibling in the same `AndFilter` group.
 *
 * This matches the semantics of upstream's outer dispatcher loop
 * (`HandlerObject.check` in `aiogram/dispatcher/event/handler.py:114-123`),
 * which *does* cascade: each `FilterObject.call` result is merged into
 * `kwargs` before the next filter is invoked.  Nesting `Filter::all`
 * therefore gives user code the same kwarg-propagation behaviour it gets
 * at the top-level dispatcher layer — no surprising gaps when filters are
 * composed.
 *
 * The contract is codified by `testKwargCascadeForwardsEarlierFilterReturnsIntoLaterFilters`
 * in `tests/Filters/Logic/AndFilterTest.php`.
 *
 * # Semantics
 *
 * 1. With zero targets, the loop is a no-op and the filter returns
 *    `true` (identity-accept).
 * 2. Each child is invoked in declaration order with the original
 *    `$kwargs` UNIONED with the kwargs every preceding child contributed
 *    via an array return (intentional widening over upstream — see above).
 * 3. A `false` vote short-circuits the chain: later children are skipped
 *    and the combinator returns `false`. (Note: unlike `HandlerObject::check`,
 *    this combinator's rejection ladder is strictly `=== false`, not
 *    arbitrary falsy values — the abstract `Filter::__invoke` return type
 *    only admits `bool|array`, so we never see `null`/`0`/`''`/`[]`.)
 * 4. After every child accepts: return the accumulated kwarg map if any
 *    child contributed entries; otherwise return `true`.
 */
final class AndFilter extends Filter
{
  /** @var list<Filter> */
  public readonly array $targets;

  public function __construct(Filter ...$targets)
  {
    // `array_values` strips any string keys named-argument unpacking
    // might leave behind, guaranteeing the readonly array is a `list`.
    $this->targets = array_values($targets);
  }

  public function __invoke(object $event, array $kwargs = []): array|bool
  {
    $merged = [];

    foreach ($this->targets as $target) {
      // Cascade: each filter sees `$kwargs` plus whatever previous
      // filters contributed. `[...$a, ...$b]` matches Python
      // `dict.update()` — later keys win on collision.
      $result = $target($event, [...$kwargs, ...$merged]);

      if ($result === false) {
        // Hard reject; later filters are NOT consulted.
        return false;
      }

      if (is_array($result)) {
        $merged = [...$merged, ...$result];
      }
      // `true`: accept-without-contribution; drop through to next target.
    }

    return $merged !== [] ? $merged : true;
  }
}
