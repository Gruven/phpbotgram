<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils;

use InvalidArgumentException;

/**
 * Tuning knobs for the long-poll retry `Backoff` — port of
 * `aiogram.utils.backoff.BackoffConfig`.
 *
 * Defaults mirror upstream `DEFAULT_BACKOFF_CONFIG` exactly
 * (`aiogram/dispatcher/dispatcher.py:35`):
 * `min=1.0s`, `max=5.0s`, `factor=1.3`, `jitter=0.1`.
 *
 * Spec deviation from `aiogram` proper:
 *
 * - **Allows `min_delay == max_delay` and `min_delay = 0.0`.** Upstream
 *   rejects `max_delay <= min_delay`; we relax that to `<` so callers can
 *   pin a constant retry delay (useful in tests and in deterministic
 *   immediate-retry strategies). We also accept `min_delay = 0.0` for the
 *   same reason — upstream silently allows it but never documents the
 *   intent, so we add an explicit non-negativity check for clarity.
 *
 * The class is `readonly`: once a Backoff is wired to a config, swapping
 * thresholds mid-flight would race with the loop's `next_delay` computation.
 * Callers wanting a new schedule must build a new config + Backoff.
 */
final readonly class BackoffConfig
{
  public function __construct(
    public float $minDelay = 1.0,
    public float $maxDelay = 5.0,
    public float $factor = 1.3,
    public float $jitter = 0.1,
  ) {
    if ($minDelay < 0.0) {
      throw new InvalidArgumentException('BackoffConfig: minDelay must be non-negative');
    }

    if ($maxDelay < $minDelay) {
      throw new InvalidArgumentException('BackoffConfig: maxDelay must be >= minDelay');
    }

    if ($factor <= 1.0) {
      throw new InvalidArgumentException('BackoffConfig: factor must be > 1.0');
    }

    if ($jitter < 0.0) {
      throw new InvalidArgumentException('BackoffConfig: jitter must be non-negative');
    }
  }
}
