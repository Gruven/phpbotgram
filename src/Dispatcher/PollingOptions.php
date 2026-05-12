<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher;

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
 * - `allowedUpdates=null` — Telegram interprets a missing key as "all
 *   subscribed update types". `null` is therefore the explicit "let
 *   Telegram filter" sentinel.
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
   * @param int $pollingTimeout Long-poll seconds for `getUpdates`. 0 means
   *                            short-poll (return immediately). Must be `>= 0`.
   * @param ?BackoffConfig $backoffConfig Retry tuning; `null` => use
   *                                      `new BackoffConfig()` (upstream `DEFAULT_BACKOFF_CONFIG`).
   * @param ?list<string> $allowedUpdates Telegram `allowed_updates`
   *                                      parameter. `null` => omit the key (receive all subscribed types).
   * @param ?int $handleAsTasks Concurrent in-flight updates per bot.
   *                            `null` => fully serial (no fiber spawn); positive int => fiber
   *                            pool of that size. Must be `>= 1` or `null`.
   */
  public function __construct(
    public int $pollingTimeout = 10,
    ?BackoffConfig $backoffConfig = null,
    public ?array $allowedUpdates = null,
    public ?int $handleAsTasks = 100,
  ) {
    $this->backoffConfig = $backoffConfig ?? new BackoffConfig();

    if ($pollingTimeout < 0) {
      throw new InvalidArgumentException('PollingOptions: pollingTimeout must be >= 0');
    }

    if ($handleAsTasks !== null && $handleAsTasks < 1) {
      throw new InvalidArgumentException('PollingOptions: handleAsTasks must be >= 1 or null');
    }
  }
}
