<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use Closure;
use Error;
use PHPUnit\Framework\Attributes\After;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Throwable;

use function Amp\async;

trait RunAsyncTrait
{
  /**
   * Drives a Fiber-aware closure to completion via EventLoop::run(), which fully
   * terminates the driver's loop fiber so that resetEventLoop() can safely swap the
   * driver in the #[After] hook without hitting the "driver is running" guard.
   *
   * The closure runs inside Amp\async() so it can suspend; any exception thrown
   * inside the closure is re-thrown here.
   *
   * @template T
   *
   * @param Closure(): T $body
   *
   * @return T
   */
  protected function runAsync(Closure $body): mixed
  {
    $result = null;
    $exception = null;

    EventLoop::queue(static function () use ($body, &$result, &$exception): void {
      try {
        $result = async($body)->await();
      } catch (Throwable $e) {
        $exception = $e;
      }
    });

    EventLoop::run();

    if ($exception !== null) {
      throw $exception;
    }

    /** @var T $result */
    return $result;
  }

  /**
   * Fresh driver per test — Revolt v1 has no public API to enumerate pending callbacks,
   * so a driver reset is the simplest reliable isolation. See spec § "Test infrastructure".
   * Callable directly from a test method that needs an explicit reset, AND fired
   * automatically after every test via the #[After] hook.
   *
   * Defensive path: some production code (e.g. MongoStorage) calls
   * `Amp\async()->await()` outside of `EventLoop::run()`, which bootstraps
   * the driver fiber internally and can leave `Driver::isRunning() === true`
   * after the call returns.  In that situation `EventLoop::setDriver()` would
   * throw "Can't swap the event loop driver while the driver is running".
   * We catch that error, force-stop the driver, and retry once.  If the
   * second attempt also throws we silently swallow it: the trait must never
   * be the source of false test errors.
   */
  #[After]
  public function resetEventLoop(): void
  {
    try {
      EventLoop::setDriver(new StreamSelectDriver());
    } catch (Error $e) {
      if (!str_contains($e->getMessage(), 'driver is running')) {
        throw $e;
      }

      // Defensive: a prior test left the driver in a running state (e.g. via
      // bare Amp\async()->await() outside EventLoop::run()).  Force-stop the
      // current driver, then retry the swap.
      try {
        EventLoop::getDriver()->stop();
      } catch (Throwable) {
        // Best-effort — ignore stop() failures.
      }

      try {
        EventLoop::setDriver(new StreamSelectDriver());
      } catch (Throwable) {
        // If the second attempt also fails, absorb the error.  The next test
        // that actually uses the loop will call resetEventLoop() again.
      }
    }
  }
}
