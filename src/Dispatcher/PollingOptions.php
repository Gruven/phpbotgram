<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher;

use Gruven\PhpBotGram\Types\Unspecified;
use Gruven\PhpBotGram\Utils\BackoffConfig;
use InvalidArgumentException;

/**
 * Tuning knobs for `Dispatcher::startPolling` — port of the constructor
 * parameters of `aiogram.dispatcher.dispatcher.Dispatcher.start_polling`
 * grouped into a single value object.
 *
 * Why a DTO instead of mirroring upstream's positional kwargs:
 *
 * - `start_polling` upstream takes 4+ tunables (timeout, backoff config,
 *   allowed_updates, handle_as_tasks, plus concurrency limit). Passing them
 *   individually leaks the entry-point signature; bundling them lets
 *   callers build a config once and reuse it across `runPolling` and
 *   `startPolling`.
 * - The `readonly` modifier means the polling loop never reads a moving
 *   target — a config swap requires building a new `PollingOptions` and
 *   restarting the loop.
 *
 * Defaults (spec § "Polling loop"):
 * - `pollingTimeout=10` — long-poll seconds passed to `getUpdates`.
 *   Upstream `Dispatcher.start_polling` defaults to 10 (the internal
 *   `_polling` helper defaults to 30, but the public entry point is 10,
 *   which is what users see).
 * - `backoffConfig=new BackoffConfig()` — matches upstream
 *   `DEFAULT_BACKOFF_CONFIG = BackoffConfig(1.0, 5.0, 1.3, 0.1)`.
 * - `allowedUpdates=Unspecified::instance()` — Fix I8 sentinel for
 *   "auto-resolve via `Router::resolveUsedUpdateTypes()` at polling
 *   start" (mirrors upstream's `UNSET` default at `dispatcher.py:526`).
 *   Distinct from `null`, which remains the explicit "send no
 *   allowed_updates key" passthrough so Telegram returns every
 *   subscribed type.
 * - `handleAsTasks=100` — concurrent in-flight updates per bot. Upstream
 *   exposes `handle_as_tasks: bool` (on/off) PLUS `tasks_concurrency_limit:
 *   int|None` (cap). Collapsing into one `int|null` slot is denser without
 *   losing information: `null` => fully serial (handle_as_tasks=False);
 *   `int n` => concurrent with semaphore of size n (handle_as_tasks=True,
 *   tasks_concurrency_limit=n).
 */
final readonly class PollingOptions
{
  public BackoffConfig $backoffConfig;

  /**
   * Telegram `allowed_updates` parameter, plus the `Unspecified` sentinel
   * for "auto-resolve at polling start". Three legal shapes:
   *
   * - `Unspecified::instance()` (default): `Dispatcher::startPolling` calls
   *   `Router::resolveUsedUpdateTypes()` and substitutes the resulting
   *   `list<string>` before kicking off the per-bot polling fibers. Mirrors
   *   upstream `dispatcher.py:564-565`.
   * - `null`: explicit opt-out — the `getUpdates` request omits the
   *   `allowed_updates` key, so Telegram returns every subscribed type.
   * - `list<string>`: a caller-curated list; passed through verbatim.
   *
   * @var null|list<string>|Unspecified
   */
  public null|array|Unspecified $allowedUpdates;

  /**
   * @param int $pollingTimeout Long-poll seconds for `getUpdates`. 0 means
   *                            short-poll (return immediately). Must be `>= 0`.
   * @param ?BackoffConfig $backoffConfig Retry tuning; `null` => use
   *                                      `new BackoffConfig()` (upstream `DEFAULT_BACKOFF_CONFIG`).
   * @param null|list<string>|Unspecified $allowedUpdates Telegram
   *                                                      `allowed_updates` parameter. See `$allowedUpdates`
   *                                                      property docblock for the three legal shapes. Omit
   *                                                      the argument to get the `Unspecified` auto-resolve
   *                                                      default (PHP can't use a function call as a default
   *                                                      value, so the constructor maps a no-arg call to the
   *                                                      sentinel in the body).
   * @param ?int $handleAsTasks Concurrent in-flight updates per bot.
   *                            `null` => fully serial (no fiber spawn); positive int => fiber
   *                            pool of that size. Must be `>= 1` or `null`.
   */
  public function __construct(
    public int $pollingTimeout = 10,
    ?BackoffConfig $backoffConfig = null,
    null|array|Unspecified $allowedUpdates = new Unspecified(),
    public ?int $handleAsTasks = 100,
  ) {
    // The default `new Unspecified()` is a fresh instance per call by
    // the language semantics; normalise to the singleton via identity
    // check so `=== Unspecified::instance()` works in callers.
    $this->allowedUpdates = $allowedUpdates instanceof Unspecified
      ? Unspecified::instance()
      : $allowedUpdates;
    $this->backoffConfig = $backoffConfig ?? new BackoffConfig();

    if ($pollingTimeout < 0) {
      throw new InvalidArgumentException('PollingOptions: pollingTimeout must be >= 0');
    }

    if ($handleAsTasks !== null && $handleAsTasks < 1) {
      throw new InvalidArgumentException('PollingOptions: handleAsTasks must be >= 1 or null');
    }
  }
}
