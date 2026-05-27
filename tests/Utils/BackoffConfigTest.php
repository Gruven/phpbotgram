<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils;

use Gruven\PhpBotGram\Utils\BackoffConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class BackoffConfigTest extends TestCase
{
  public function testDefaultsMatchUpstreamSpec(): void
  {
    // Mirrors aiogram/dispatcher/dispatcher.py:
    //   DEFAULT_BACKOFF_CONFIG = BackoffConfig(min_delay=1.0, max_delay=5.0, factor=1.3, jitter=0.1)
    $config = new BackoffConfig();
    self::assertSame(1.0, $config->minDelay);
    self::assertSame(5.0, $config->maxDelay);
    self::assertSame(1.3, $config->factor);
    self::assertSame(0.1, $config->jitter);
  }

  public function testCustomValuesAreStored(): void
  {
    $config = new BackoffConfig(minDelay: 0.5, maxDelay: 30.0, factor: 2.0, jitter: 0.25);
    self::assertSame(0.5, $config->minDelay);
    self::assertSame(30.0, $config->maxDelay);
    self::assertSame(2.0, $config->factor);
    self::assertSame(0.25, $config->jitter);
  }

  public function testZeroMinDelayIsAccepted(): void
  {
    // A non-blocking backoff (e.g. unit tests, immediate-retry strategies)
    // legitimately wants min_delay=0.0; only strictly-negative values are
    // rejected.
    $config = new BackoffConfig(minDelay: 0.0, maxDelay: 1.0);
    self::assertSame(0.0, $config->minDelay);
  }

  /**
   * @return iterable<string, array{float, float, float, float, string}>
   */
  public static function invalidConfigs(): iterable
  {
    yield 'negative minDelay' => [-0.1, 1.0, 1.3, 0.1, 'minDelay must be non-negative'];

    yield 'maxDelay below minDelay' => [2.0, 1.0, 1.3, 0.1, 'maxDelay must be >= minDelay'];

    yield 'factor equal to 1' => [1.0, 5.0, 1.0, 0.1, 'factor must be > 1.0'];

    yield 'factor below 1' => [1.0, 5.0, 0.5, 0.1, 'factor must be > 1.0'];

    yield 'negative jitter' => [1.0, 5.0, 1.3, -0.01, 'jitter must be non-negative'];
  }

  #[DataProvider('invalidConfigs')]
  public function testInvalidConfigRejected(
    float $minDelay,
    float $maxDelay,
    float $factor,
    float $jitter,
    string $messageNeedle,
  ): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage($messageNeedle);
    new BackoffConfig(minDelay: $minDelay, maxDelay: $maxDelay, factor: $factor, jitter: $jitter);
  }

  public function testMinEqualMaxIsAccepted(): void
  {
    // Edge case: with min == max, the backoff is effectively a constant
    // delay. Allowed because the cap math (`min(max, current*factor+jitter)`)
    // still works.
    $config = new BackoffConfig(minDelay: 2.0, maxDelay: 2.0);
    self::assertSame(2.0, $config->minDelay);
    self::assertSame(2.0, $config->maxDelay);
  }
}
