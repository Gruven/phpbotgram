<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils;

use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use Gruven\PhpBotGram\Utils\Backoff;
use Gruven\PhpBotGram\Utils\BackoffConfig;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class BackoffTest extends TestCase
{
  use RunAsyncTrait;

  public function testInitialStateMirrorsMinDelay(): void
  {
    // Upstream Backoff.__init__ seeds both `_next_delay` and `_current_delay`
    // with `config.min_delay`, so a freshly-constructed backoff exposes the
    // min delay as both current and next.
    $backoff = new Backoff(new BackoffConfig(minDelay: 1.0, maxDelay: 5.0, factor: 1.3, jitter: 0.1));
    self::assertSame(1.0, $backoff->currentDelay());
    self::assertSame(1.0, $backoff->nextDelay());
    self::assertSame(0, $backoff->counter);
  }

  public function testNextReturnsCurrentAndAdvances(): void
  {
    // Deterministic config: jitter=0.0, factor=2.0. With initial state
    // current=next=1.0, the first next() returns 1.0 and computes
    // next_delay = min(5.0, 1.0 * 2.0 + 0.0) = 2.0.
    $backoff = new Backoff(new BackoffConfig(minDelay: 1.0, maxDelay: 5.0, factor: 2.0, jitter: 0.0));

    $first = $backoff->next();
    self::assertSame(1.0, $first);
    self::assertSame(1.0, $backoff->currentDelay());
    self::assertSame(2.0, $backoff->nextDelay());
    self::assertSame(1, $backoff->counter);
  }

  public function testGrowthIsExponentialWithoutJitter(): void
  {
    // Deterministic sequence: 1, 2, 4, 5, 5, 5 (capped at maxDelay=5.0).
    $backoff = new Backoff(new BackoffConfig(minDelay: 1.0, maxDelay: 5.0, factor: 2.0, jitter: 0.0));

    self::assertSame(1.0, $backoff->next());
    self::assertSame(2.0, $backoff->next());
    self::assertSame(4.0, $backoff->next());
    self::assertSame(5.0, $backoff->next());
    self::assertSame(5.0, $backoff->next());
    self::assertSame(5.0, $backoff->next());
    self::assertSame(6, $backoff->counter);
  }

  public function testDelayCappedAtMaxDelay(): void
  {
    // With factor large enough to blow past maxDelay on the very first
    // step, currentDelay must clamp at maxDelay forever.
    $backoff = new Backoff(new BackoffConfig(minDelay: 1.0, maxDelay: 3.0, factor: 10.0, jitter: 0.0));

    self::assertSame(1.0, $backoff->next());
    self::assertSame(3.0, $backoff->next());
    self::assertSame(3.0, $backoff->next());
    self::assertSame(3.0, $backoff->nextDelay());
  }

  public function testJitterStaysWithinBounds(): void
  {
    // The implementation computes:
    //   nextDelay = min(maxDelay, currentDelay * factor + jitter_draw)
    // where jitter_draw ∈ [-jitter, +jitter].
    // The MIN clamp is applied AFTER adding jitter, so the bounds on
    // the observed value are:
    //   lower = min(maxDelay, rawTarget - jitter)
    //   upper = min(maxDelay, rawTarget + jitter)
    // (when rawTarget + jitter <= maxDelay, the cap is inert and the
    // window is `[raw - j, raw + j]`; once we saturate the cap pins
    // both sides to maxDelay — except the lower side, which can dip
    // below maxDelay if the draw is sufficiently negative.)
    $config = new BackoffConfig(minDelay: 1.0, maxDelay: 5.0, factor: 1.3, jitter: 0.1);
    $backoff = new Backoff($config);

    for ($i = 0; $i < 50; ++$i) {
      $backoff->next();
      $rawTarget = $backoff->currentDelay() * $config->factor;
      $expectedLower = min($config->maxDelay, $rawTarget - $config->jitter);
      $expectedUpper = min($config->maxDelay, $rawTarget + $config->jitter);
      self::assertGreaterThanOrEqual($expectedLower - 1e-9, $backoff->nextDelay());
      self::assertLessThanOrEqual($expectedUpper + 1e-9, $backoff->nextDelay());
      // And the absolute cap is never violated.
      self::assertLessThanOrEqual($config->maxDelay, $backoff->nextDelay());
    }
  }

  public function testResetRestoresInitialState(): void
  {
    $backoff = new Backoff(new BackoffConfig(minDelay: 1.0, maxDelay: 5.0, factor: 2.0, jitter: 0.0));
    $backoff->next();
    $backoff->next();
    $backoff->next();
    self::assertSame(3, $backoff->counter);
    self::assertNotSame(1.0, $backoff->currentDelay());

    $backoff->reset();
    self::assertSame(0, $backoff->counter);
    self::assertSame(1.0, $backoff->currentDelay());
    self::assertSame(1.0, $backoff->nextDelay());
  }

  public function testAsleepActuallySleepsAndAdvances(): void
  {
    // We pick min/max=0.001 (1ms) so the test stays fast while still
    // measurably suspending the fiber. The post-condition is that asleep
    // advanced the counter and currentDelay matches the configured value
    // (no factor growth possible because min==max with jitter=0).
    $backoff = new Backoff(new BackoffConfig(minDelay: 0.001, maxDelay: 0.001, factor: 2.0, jitter: 0.0));

    $start = hrtime(true);
    $this->runAsync(static function () use ($backoff): void {
      $backoff->asleep();
    });
    $elapsedNs = hrtime(true) - $start;

    // Allow generous slack for CI jitter, but verify SOME suspension occurred.
    self::assertGreaterThanOrEqual(500_000, $elapsedNs, 'asleep() must actually suspend (>=0.5ms)');
    self::assertSame(1, $backoff->counter);
    self::assertSame(0.001, $backoff->currentDelay());
  }
}
