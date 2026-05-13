<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\Logic;

use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\TelegramObject;

/**
 * "Every child must accept" combinator with a kwarg cascade. Mirrors
 * upstream `aiogram.filters.logic._AndFilter` at `aiogram/filters/logic.py`.
 *
 * Semantics, matching upstream `__call__` line-by-line:
 *
 * 1. With zero targets, the loop is a no-op and the filter returns
 *    `true` (identity-accept).
 * 2. Each child is invoked in declaration order with the original
 *    `$kwargs` UNIONED with the kwargs every preceding child contributed
 *    via an array return. Mirrors `target(*args, **kwargs, **final_result)`.
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

  public function __invoke(TelegramObject $event, array $kwargs = []): array|bool
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
