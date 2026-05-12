<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

/**
 * Wraps a filter callback that votes on whether a handler should run for a
 * given update. Mirrors upstream
 * `aiogram.dispatcher.event.handler.FilterObject`.
 *
 * A filter callback may return:
 *
 * - `false` (or any falsy value: `null`, `0`, `''`, `[]`) — reject; the
 *   handler is skipped and later filters in the same handler don't run.
 * - `true` (or any truthy non-array: `1`, non-empty string, object) — accept;
 *   no extra kwargs are injected for downstream consumption.
 * - `array<string, mixed>` (associative) — accept and merge the entries into
 *   the kwargs bag that flows to subsequent filters and the eventual handler
 *   (this is how command/regex match data gets threaded into handlers
 *   upstream).
 *
 * The result-interpretation logic itself lives in `HandlerObject::check()` —
 * FilterObject is intentionally a thin marker over `CallableObject` so its
 * inherited `call()` can be used uniformly inside that pipeline. Phase 4
 * will widen this class to bind a `MagicFilter` value into the underlying
 * callback when registered with the `F`-builder DSL — until then it adds
 * nothing of its own beyond the inheritance.
 */
final class FilterObject extends CallableObject {}
