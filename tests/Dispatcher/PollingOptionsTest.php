<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher;

use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Types\Unspecified;
use Gruven\PhpBotGram\Utils\BackoffConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PollingOptionsTest extends TestCase
{
  public function testDefaultsMatchSpec(): void
  {
    // Spec § "Polling loop": pollingTimeout=10s long-poll, default-tuned
    // backoff, allowed_updates defaults to the Unspecified sentinel
    // (Dispatcher::startPolling resolves it via Router::resolveUsedUpdateTypes
    // — mirrors upstream's `UNSET` default at `dispatcher.py:526`), and
    // handle_as_tasks=100 concurrent in-flight tasks per bot.
    $options = new PollingOptions();
    self::assertSame(10, $options->pollingTimeout);
    self::assertSame(Unspecified::instance(), $options->allowedUpdates);
    self::assertSame(100, $options->handleAsTasks);
    self::assertInstanceOf(BackoffConfig::class, $options->backoffConfig);
  }

  public function testDefaultBackoffConfigMatchesUpstreamSpec(): void
  {
    // When no BackoffConfig is supplied, PollingOptions must wire a default
    // matching aiogram's DEFAULT_BACKOFF_CONFIG so polling behaviour is
    // identical out of the box.
    $options = new PollingOptions();
    self::assertSame(1.0, $options->backoffConfig->minDelay);
    self::assertSame(5.0, $options->backoffConfig->maxDelay);
    self::assertSame(1.3, $options->backoffConfig->factor);
    self::assertSame(0.1, $options->backoffConfig->jitter);
  }

  public function testCustomBackoffConfigIsStored(): void
  {
    $backoff = new BackoffConfig(minDelay: 0.5, maxDelay: 30.0, factor: 2.0, jitter: 0.0);
    $options = new PollingOptions(backoffConfig: $backoff);
    self::assertSame($backoff, $options->backoffConfig);
  }

  public function testCustomValuesStored(): void
  {
    $options = new PollingOptions(
      pollingTimeout: 30,
      allowedUpdates: ['message', 'callback_query'],
      handleAsTasks: 10,
    );
    self::assertSame(30, $options->pollingTimeout);
    self::assertSame(['message', 'callback_query'], $options->allowedUpdates);
    self::assertSame(10, $options->handleAsTasks);
  }

  public function testNullHandleAsTasksDisablesTaskSpawning(): void
  {
    // null is the explicit opt-out: serial in-loop dispatch instead of
    // concurrent fibers. Validation must NOT reject it.
    $options = new PollingOptions(handleAsTasks: null);
    self::assertNull($options->handleAsTasks);
  }

  public function testZeroPollingTimeoutAccepted(): void
  {
    // 0 is a valid `getUpdates` timeout (short-poll). Only strictly
    // negative is rejected.
    $options = new PollingOptions(pollingTimeout: 0);
    self::assertSame(0, $options->pollingTimeout);
  }

  public function testRejectsNegativePollingTimeout(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('pollingTimeout must be >= 0');
    new PollingOptions(pollingTimeout: -1);
  }

  public function testRejectsZeroHandleAsTasks(): void
  {
    // 0 concurrent workers is nonsensical — distinct from null (off). The
    // constructor must reject it so a config typo doesn't silently stall
    // the polling loop forever.
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('handleAsTasks must be >= 1 or null');
    new PollingOptions(handleAsTasks: 0);
  }

  public function testRejectsNegativeHandleAsTasks(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('handleAsTasks must be >= 1 or null');
    new PollingOptions(handleAsTasks: -5);
  }

  public function testNullAllowedUpdatesIsValidExplicitOptOut(): void
  {
    // Fix I8: `null` remains the explicit "send the empty key / receive all
    // subscribed types" passthrough. Distinct from the Unspecified default
    // (auto-resolve via Router::resolveUsedUpdateTypes).
    $options = new PollingOptions(allowedUpdates: null);
    self::assertNull($options->allowedUpdates);
  }

  public function testExplicitListAllowedUpdatesStoredAsIs(): void
  {
    // A caller-supplied list of update types skips the auto-resolve and
    // skips the "receive all" passthrough — Telegram only sends the listed
    // types.
    $options = new PollingOptions(allowedUpdates: ['message', 'callback_query']);
    self::assertSame(['message', 'callback_query'], $options->allowedUpdates);
  }
}
