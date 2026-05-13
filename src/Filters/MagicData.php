<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

use Gruven\PhpBotGram\Utils\MagicFilter\AttrDict;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;

/**
 * Filter that resolves a `MagicFilter` chain against the dispatch data dict,
 * i.e. `['event' => $event, ...$kwargs]`. Allows chain expressions to reach
 * into both the event payload AND the dispatcher's contextual kwargs (FSM
 * state, storage, bot, anything middleware injected):
 *
 *   F->event->text->equals('hi')          // navigate event payload
 *   F->state->equals(SomeState::Active)   // navigate FSM kwarg (Phase 5+)
 *   F->fsm_storage->equals(null)          // any kwarg by name
 *
 * 1-for-1 port of upstream `aiogram.filters.magic_data.MagicData`
 * (`aiogram/filters/magic_data.py`). Upstream wraps `{'event': event,
 * **kwargs}` in `magic_filter.AttrDict` before handing it to
 * `MagicFilter.resolve`. The PHP port mirrors that exactly — kwargs flow
 * through our own `AttrDict` (which mirrors `magic_filter.AttrDict`) so
 * property-style access (`F->state`) and subscript-style access
 * (`F->item('state')`) both work transparently.
 *
 * Distinct from `MagicFilterAsFilter`: that bridge resolves a chain against
 * the EVENT directly (`F->id->equals(...)`); this filter resolves against
 * the WIDER kwargs bag with the event keyed under `event`. Use this filter
 * when a rule needs to depend on contextual kwargs alongside (or instead of)
 * the event payload.
 *
 * Result interpretation matches the bridge: `null`/`false` → reject,
 * kwarg-shaped array → accept with merged kwargs, any other truthy value
 * → plain accept.
 */
final class MagicData extends Filter
{
  public function __construct(public readonly MagicFilter $magicData) {}

  public function __invoke(object $event, array $kwargs = []): array|bool
  {
    // Upstream `MagicData.__call__` builds `{'event': event, **kwargs}`
    // and resolves the chain against an `AttrDict` wrapping that dict.
    // We do the same: array spread builds the dispatch data dict (event
    // first; later kwargs of the same key would overwrite, matching
    // Python's dict-merge order) and `AttrDict` is the bridge between
    // `MagicFilter`'s `__get`/`item()` semantics and our `array` data.
    $data = new AttrDict(['event' => $event, ...$kwargs]);

    try {
      $result = $this->magicData->resolve($data);
    } catch (RejectOperations) {
      // A rejection that escaped `resolve()` (no important op rescued
      // it) collapses to a hard reject — mirrors `MagicFilterAsFilter`.
      return false;
    }

    if ($result === null) {
      return false;
    }

    if (is_array($result)) {
      if ($result === []) {
        return false;
      }

      // Detect kwarg-shaped arrays (string keys) vs payload arrays. Only
      // string-keyed entries propagate as handler kwargs; numeric-keyed
      // results coerce to bool (truthy = accept). Matches the bridge's
      // rules so both code paths agree on what a chain "accepted with
      // kwargs" looks like.
      $stringKeyed = [];

      foreach ($result as $key => $value) {
        if (is_string($key)) {
          $stringKeyed[$key] = $value;
        }
      }

      if ($stringKeyed !== []) {
        return $stringKeyed;
      }

      return true;
    }

    return (bool)$result;
  }
}
