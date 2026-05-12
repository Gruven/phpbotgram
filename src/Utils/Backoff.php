<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils;

use function Amp\delay;

/**
 * Stateful exponential-backoff helper for the long-poll retry loop — port of
 * `aiogram.utils.backoff.Backoff`.
 *
 * Semantics (matching upstream `Backoff.__next__`):
 *
 * 1. `__construct` seeds both `currentDelay` and `nextDelay` with
 *    `config.minDelay`. The first `next()` call therefore returns
 *    `minDelay`, mirroring upstream where the first iteration sleeps the
 *    minimum and only subsequent iterations grow.
 * 2. `next()` returns the (now-current) delay AND advances state. It is
 *    the only mutator besides `reset()`.
 * 3. Growth is `min(maxDelay, currentDelay * factor + jitter)`, with
 *    `jitter` drawn uniformly from `[-config.jitter, +config.jitter]`.
 *    Upstream uses `normalvariate(mean=min(value*factor, max_delay), sigma=jitter)`
 *    — we deviate here:
 *
 *    **Spec deviation: uniform jitter, not normal jitter.** Normal jitter
 *    can spike above maxDelay (unbounded tail), forcing a separate clamp.
 *    Uniform jitter in `[-jitter, +jitter]` is bounded and gives the same
 *    "smear the retry burst" guarantee callers care about. The cap is
 *    applied AFTER the jitter is added (`min(max, value*factor + jitter)`)
 *    so nextDelay can never exceed maxDelay regardless of jitter draw.
 *
 *    The deviation is invisible to consumers: only the statistical shape
 *    of the per-call delay differs, never the bounds.
 *
 * 4. `asleep()` suspends the current Fiber via `Amp\delay()` for
 *    `currentDelay` seconds, then advances. This is the loop's primary
 *    entry point; `next()` and `reset()` exist for tests and for callers
 *    that want to drive the schedule manually.
 *
 * Not `final readonly`: the instance is mutable by design (counter,
 * delay state). `BackoffConfig` is the immutable half.
 */
final class Backoff
{
  /**
   * Number of times `next()` (or `asleep()`) has advanced the schedule.
   * Reset to 0 by `reset()`. Public — upstream exposes `counter` as a
   * read-only property; PHP readonly on an int counter would require a
   * setter ceremony that costs more than it saves here.
   */
  public int $counter = 0;

  private float $currentDelay;

  private float $nextDelay;

  public function __construct(public readonly BackoffConfig $config)
  {
    $this->currentDelay = $config->minDelay;
    $this->nextDelay = $config->minDelay;
  }

  public function currentDelay(): float
  {
    return $this->currentDelay;
  }

  public function nextDelay(): float
  {
    return $this->nextDelay;
  }

  /**
   * Advance one step: rotate `nextDelay -> currentDelay`, compute the
   * new `nextDelay`, bump the counter, and return the (now-current)
   * delay so callers can pass it to `sleep`/`delay`.
   *
   * Upstream calls this `__next__` because Python iterates the backoff
   * via `next(backoff)`. PHP has no equivalent magic; `next()` is the
   * plain name we expose.
   */
  public function next(): float
  {
    $delay = $this->currentDelay = $this->nextDelay;
    ++$this->counter;

    // Symmetric uniform jitter in [-jitter, +jitter]. mt_rand()/mt_getrandmax()
    // is fine — backoff doesn't need cryptographic randomness; the only
    // requirement is that concurrent bots don't retry in lockstep.
    $jitter = $this->config->jitter > 0.0
      ? ((mt_rand() / mt_getrandmax()) * 2 - 1) * $this->config->jitter
      : 0.0;

    $this->nextDelay = min(
      $this->config->maxDelay,
      $this->currentDelay * $this->config->factor + $jitter,
    );

    return $delay;
  }

  public function reset(): void
  {
    $this->counter = 0;
    $this->currentDelay = $this->config->minDelay;
    $this->nextDelay = $this->config->minDelay;
  }

  /**
   * Suspend the calling Fiber for `currentDelay` seconds via `Amp\delay()`,
   * then advance the schedule. The Revolt event loop continues servicing
   * other fibers during the wait — this is the cooperative analogue of
   * `time.sleep` from upstream's sync `sleep()`, and the direct port of
   * upstream's async `asleep`.
   */
  public function asleep(): void
  {
    delay($this->next());
  }
}
