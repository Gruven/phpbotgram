<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Webhook;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\RecordingDispatcher;
use Gruven\PhpBotGram\Webhook\BaseRequestHandler;
use Gruven\PhpBotGram\Webhook\Setup;
use PHPUnit\Framework\TestCase;

/**
 * Tests for {@see Setup::register()}.
 *
 * Uses a {@see SpyHttpServer} that records `onStart`/`onStop` callbacks and
 * invokes them manually — no real socket is ever opened.
 *
 * Upstream `tests/test_webhook/test_aiohttp_server.py` `TestAiohttpServer::test_setup_application`
 * cases deliberately not ported:
 *
 * - `TestAiohttpServer::test_setup_application` — API divergence: the upstream
 *   test asserts `len(app.on_startup) == 2` and `len(app.on_shutdown) == 1` by
 *   inspecting aiohttp's observer list directly. PHP uses amphp's `onStart`/`onStop`
 *   callbacks, which are not exposed as a countable list. The equivalent behavior —
 *   that exactly one `onStart` and one `onStop` callback are registered and invoked
 *   correctly — is covered by `testRegisterAttachesOnStartCallback`,
 *   `testRegisterAttachesOnStopCallback`, `testOnStartFiresEmitStartup`, and
 *   `testOnStopFiresEmitShutdown`.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 *
 * @coversNothing
 */
final class SetupTest extends TestCase
{
  // =========================================================================
  // Helpers
  // =========================================================================

  /** Build a concrete stub of BaseRequestHandler that records register() calls. */
  private function makeHandler(): BaseRequestHandler
  {
    return new class extends BaseRequestHandler {
      /** @var list<string> */
      public array $registeredPaths = [];

      public function __construct()
      {
        parent::__construct(
          new RecordingDispatcher(disableFsm: true),
          false,
        );
      }

      public function close(): void {}

      public function resolveBot(Request $request): Bot
      {
        return new MockedBot();
      }

      public function verifySecret(string $telegramSecretToken, Bot $bot): bool
      {
        return true;
      }

      public function register(callable $registerRoute, string $path): void
      {
        $this->registeredPaths[] = $path;
        parent::register($registerRoute, $path);
      }
    };
  }

  // =========================================================================
  // register() wires the route
  // =========================================================================

  public function testRegisterCallsHandlerRegisterWithCorrectPath(): void
  {
    /** @var BaseRequestHandler&object{registeredPaths: list<string>} $handler */
    $handler = $this->makeHandler();
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $server = new SpyHttpServer();

    $registeredRoutes = [];
    $registerRoute = static function (string $path, RequestHandler $h) use (&$registeredRoutes): void {
      $registeredRoutes[] = $path;
    };

    Setup::register($server, $registerRoute, $dispatcher, $handler, '/webhook');

    self::assertSame(['/webhook'], $handler->registeredPaths);
    self::assertSame(['/webhook'], $registeredRoutes);
  }

  public function testRegisterUsesDefaultPathWhenNoneSupplied(): void
  {
    /** @var BaseRequestHandler&object{registeredPaths: list<string>} $handler */
    $handler = $this->makeHandler();
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $server = new SpyHttpServer();

    $registeredRoutes = [];
    $registerRoute = static function (string $path, RequestHandler $h) use (&$registeredRoutes): void {
      $registeredRoutes[] = $path;
    };

    // Omit $path — default '/webhook' should be used.
    Setup::register($server, $registerRoute, $dispatcher, $handler);

    self::assertSame(['/webhook'], $registeredRoutes);
  }

  // =========================================================================
  // register() wires onStart / onStop callbacks
  // =========================================================================

  public function testRegisterAttachesOnStartCallback(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $server = new SpyHttpServer();
    $handler = $this->makeHandler();

    Setup::register($server, static fn(string $p, RequestHandler $h) => null, $dispatcher, $handler);

    self::assertCount(1, $server->startCallbacks, 'Exactly one onStart callback must be registered');
  }

  public function testRegisterAttachesOnStopCallback(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $server = new SpyHttpServer();
    $handler = $this->makeHandler();

    Setup::register($server, static fn(string $p, RequestHandler $h) => null, $dispatcher, $handler);

    self::assertCount(1, $server->stopCallbacks, 'Exactly one onStop callback must be registered');
  }

  // =========================================================================
  // Lifecycle callbacks fire dispatcher emitStartup / emitShutdown
  // =========================================================================

  public function testOnStartFiresEmitStartup(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $startupFired = false;

    $dispatcher->startup->register(static function () use (&$startupFired): void {
      $startupFired = true;
    });

    $server = new SpyHttpServer();
    $handler = $this->makeHandler();

    Setup::register($server, static fn(string $p, RequestHandler $h) => null, $dispatcher, $handler);

    foreach ($server->startCallbacks as $cb) {
      $cb($server);
    }

    self::assertTrue($startupFired, 'emitStartup must be called when onStart fires');
  }

  public function testOnStopFiresEmitShutdown(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $shutdownFired = false;

    $dispatcher->shutdown->register(static function () use (&$shutdownFired): void {
      $shutdownFired = true;
    });

    $server = new SpyHttpServer();
    $handler = $this->makeHandler();

    Setup::register($server, static fn(string $p, RequestHandler $h) => null, $dispatcher, $handler);

    foreach ($server->stopCallbacks as $cb) {
      $cb($server);
    }

    self::assertTrue($shutdownFired, 'emitShutdown must be called when onStop fires');
  }

  public function testOnStopCallsHandlerClose(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $server = new SpyHttpServer();

    $handler = new class extends BaseRequestHandler {
      public bool $closedCalled = false;

      public function __construct()
      {
        parent::__construct(new RecordingDispatcher(disableFsm: true), false);
      }

      public function close(): void
      {
        $this->closedCalled = true;
      }

      public function resolveBot(Request $request): Bot
      {
        return new MockedBot();
      }

      public function verifySecret(string $telegramSecretToken, Bot $bot): bool
      {
        return true;
      }
    };

    Setup::register($server, static fn(string $p, RequestHandler $h) => null, $dispatcher, $handler);

    foreach ($server->stopCallbacks as $cb) {
      $cb($server);
    }

    self::assertTrue($handler->closedCalled, 'handler->close() must be called when onStop fires');
  }

  // =========================================================================
  // Shutdown order: emitShutdown before close() (polling-mode parity)
  // =========================================================================

  /**
   * Verifies that the onStop callback fires `emitShutdown` BEFORE calling
   * `handler->close()`, matching the order used by `Dispatcher::run` in
   * polling mode.
   *
   * A shutdown observer that issues a final API call must see an open session;
   * calling `close()` first would force a lazy session rebuild inside the
   * observer, defeating the intent of close.
   */
  public function testOnStopFiresEmitShutdownBeforeClose(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $server = new SpyHttpServer();

    // Shared ordered log. The shutdown observer appends via use(&); the handler
    // appends via an injected Closure so the anonymous class avoids storing a
    // bare `object` property (which PHPStan cannot resolve property names on).
    /** @var list<string> $callOrder */
    $callOrder = [];

    $dispatcher->shutdown->register(static function () use (&$callOrder): void {
      $callOrder[] = 'shutdown';
    });

    // Inject a recorder closure so close() appends without needing a typed
    // object property on the anonymous class.
    $recordClose = static function () use (&$callOrder): void {
      $callOrder[] = 'close';
    };

    $handler = new class ($recordClose) extends BaseRequestHandler {
      public function __construct(private readonly Closure $recordClose)
      {
        parent::__construct(new RecordingDispatcher(disableFsm: true), false);
      }

      public function close(): void
      {
        ($this->recordClose)();
      }

      public function resolveBot(Request $request): Bot
      {
        return new MockedBot();
      }

      public function verifySecret(string $telegramSecretToken, Bot $bot): bool
      {
        return true;
      }
    };

    Setup::register($server, static fn(string $p, RequestHandler $h) => null, $dispatcher, $handler);

    foreach ($server->stopCallbacks as $cb) {
      $cb($server);
    }

    self::assertSame(['shutdown', 'close'], $callOrder, 'emitShutdown must fire before close()');
    $shutdownPos = array_search('shutdown', $callOrder, true);
    $closePos = array_search('close', $callOrder, true);
    self::assertIsInt($shutdownPos);
    self::assertIsInt($closePos);
    self::assertLessThan($closePos, $shutdownPos, "'shutdown' must precede 'close' in call order");
  }

  public function testStartupNotFiredBeforeOnStartInvoked(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $startupFired = false;

    $dispatcher->startup->register(static function () use (&$startupFired): void {
      $startupFired = true;
    });

    $server = new SpyHttpServer();
    $handler = $this->makeHandler();

    Setup::register($server, static fn(string $p, RequestHandler $h) => null, $dispatcher, $handler);

    // Do NOT invoke onStart callbacks.
    self::assertFalse($startupFired, 'emitStartup must NOT fire until the server calls onStart');
  }

  public function testWorkflowDataForwardedToStartup(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);

    /** @var null|array<string,mixed> $capturedData */
    $capturedData = null;

    // Use variadic capture to receive all kwargs from emitStartup.
    $dispatcher->startup->register(static function (mixed ...$rest) use (&$capturedData): void {
      $capturedData = $rest;
    });

    $server = new SpyHttpServer();
    $handler = $this->makeHandler();
    $workflowData = ['db' => 'sqlite'];

    Setup::register(
      $server,
      static fn(string $p, RequestHandler $h) => null,
      $dispatcher,
      $handler,
      '/hook',
      $workflowData,
    );

    foreach ($server->startCallbacks as $cb) {
      $cb($server);
    }

    // The dispatcher injects `router => $dispatcher` into kwargs, so we
    // check for the presence of our custom key.
    self::assertIsArray($capturedData);
    self::assertSame('sqlite', $capturedData['db'] ?? null);
  }

  // =========================================================================
  // Dispatcher::workflowData merged into startup/shutdown kwargs (#1 fix)
  // =========================================================================

  public function testWorkflowDataMergedWithDispatcherWorkflowData(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    // Pre-load a value on the dispatcher's own workflowData.
    $dispatcher->workflowData['db'] = 'pdo-instance';

    /** @var null|array<string,mixed> $capturedStartup */
    $capturedStartup = null;

    $dispatcher->startup->register(
      static function (mixed ...$rest) use (&$capturedStartup): void {
        $capturedStartup = $rest;
      }
    );

    $server = new SpyHttpServer();
    $handler = $this->makeHandler();

    Setup::register(
      $server,
      static fn(string $p, RequestHandler $h) => null,
      $dispatcher,
      $handler,
      '/webhook',
      ['extra' => 'x'],   // per-call workflow data
    );

    foreach ($server->startCallbacks as $cb) {
      $cb($server);
    }

    self::assertIsArray($capturedStartup);
    self::assertSame('pdo-instance', $capturedStartup['db'] ?? null, 'dispatcher->workflowData must be merged');
    self::assertSame('x', $capturedStartup['extra'] ?? null, 'per-call workflowData must be merged');
    self::assertSame($dispatcher, $capturedStartup['dispatcher'] ?? null, 'dispatcher must be injected');
  }

  public function testPerCallWorkflowDataOverridesDispatcherDefault(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $dispatcher->workflowData['db'] = 'default';

    /** @var null|array<string,mixed> $capturedStartup */
    $capturedStartup = null;

    $dispatcher->startup->register(
      static function (mixed ...$rest) use (&$capturedStartup): void {
        $capturedStartup = $rest;
      }
    );

    $server = new SpyHttpServer();
    $handler = $this->makeHandler();

    Setup::register(
      $server,
      static fn(string $p, RequestHandler $h) => null,
      $dispatcher,
      $handler,
      '/webhook',
      ['db' => 'override'],  // per-call wins on collision
    );

    foreach ($server->startCallbacks as $cb) {
      $cb($server);
    }

    self::assertIsArray($capturedStartup);
    self::assertSame('override', $capturedStartup['db'] ?? null, 'per-call workflowData must override dispatcher default');
  }
}
