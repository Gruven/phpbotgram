<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use Closure;
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
     * @param Closure(): T $body
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
     */
    #[\PHPUnit\Framework\Attributes\After]
    public function resetEventLoop(): void
    {
        EventLoop::setDriver(new StreamSelectDriver());
    }
}
