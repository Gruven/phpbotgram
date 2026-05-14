<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Closure;
use Error;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Session\AmphpSession;
use Gruven\PhpBotGram\Client\Session\Middleware\BaseRequestMiddleware;
use Gruven\PhpBotGram\Exceptions\TokenValidationException;
use Gruven\PhpBotGram\Methods\GetMe;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Upstream: tests/test_api/test_client/test_bot.py
 *
 * Upstream skips:
 *   - test_bot_context_manager_over_session — API divergence (a): upstream uses
 *     Python `async with` for session lifecycle; PHP uses an explicit `close()`
 *     call. PHP's `Bot::context()` wraps a Closure and is not an async context
 *     manager.
 *   - test_context_manager[close=True/False] — same reason.
 *   - test_close — API divergence (a): closing the session is synchronous in
 *     PHP (AmphpSession::close()); there is no AsyncMock equivalent. The
 *     behaviour is covered by BotDownloadTest via session lifecycle.
 *   - test_emit — API divergence (a): upstream uses AsyncMock to intercept
 *     `AiohttpSession.make_request`; PHP's MockedSession does the same without
 *     async infrastructure. Basic dispatch is covered by BotSmokeTest.
 *   - test_download_file / test_download_file_default_destination /
 *     test_download_file_custom_destination / test_download — API divergence (a):
 *     all four are ported in BotDownloadTest.php.
 *   - test_download_local_file — phase scope deferral (b): local-server file
 *     download through AmphpSession requires a live server; test uses a real
 *     temp-file path and a live HTTP response. Covered structurally in
 *     BotDownloadTest::testDownloadFileReturnsBufferedBody via MockedSession.
 */
final class BotTest extends TestCase
{
  // ── test_init ───────────────────────────────────────────────────────────────

  /**
   * Upstream: test_init — default session is AmphpSession (PHP equivalent of
   * AiohttpSession), token prefix parses to bot id.
   */
  public function testInitCreatesAmphpSessionAndParsesId(): void
  {
    $bot = new Bot('42:TEST');
    self::assertInstanceOf(AmphpSession::class, $bot->session);
    self::assertSame(42, $bot->getId());
  }

  // ── test_init_default ───────────────────────────────────────────────────────

  /**
   * Upstream: test_init_default — deprecated flat kwargs (parse_mode, etc.)
   * raise TypeError when passed directly to Bot constructor. In PHP, passing
   * unknown named arguments raises Error.
   *
   * @param array<string, mixed> $args
   */
  #[DataProvider('invalidBotKwargsProvider')]
  public function testInitWithUnknownNamedArgRaisesError(array $args): void
  {
    $this->expectException(Error::class);
    // Simulate passing unknown named args; PHP raises Error for unknown named params.
    $this->callBotWithNamedArgs($args);
  }

  /**
   * @return array<string, array{array<string, mixed>}>
   */
  public static function invalidBotKwargsProvider(): array
  {
    return [
      'parse_mode only' => [['parse_mode' => 'HTML']],
      'disable_web_page_preview only' => [['disable_web_page_preview' => true]],
      'protect_content only' => [['protect_content' => true]],
      'parse_mode + disable_web_page_preview' => [['parse_mode' => 'HTML', 'disable_web_page_preview' => true]],
    ];
  }

  /**
   * @param array<string, mixed> $args
   */
  private function callBotWithNamedArgs(array $args): void
  {
    // Unknown named arguments to Bot() raise `Error: Unknown named parameter $<name>`.
    // We use ReflectionClass::newInstanceArgs with named-key array in PHP 8+,
    // which triggers the same Error as a direct named-arg call.
    $allArgs = ['token' => '42:TEST'] + $args;
    $rc = new ReflectionClass(Bot::class);
    $rc->newInstanceArgs($allArgs);
  }

  // ── test_hashable ───────────────────────────────────────────────────────────

  /**
   * Upstream: test_hashable — hash(bot) == hash("42:TEST"). In PHP there is no
   * built-in `hash()` for objects. The PHP port exposes the token directly as
   * `Bot::$token` and treats two Bot instances as equal when they share the
   * same token string.
   */
  public function testBotTokenIsPublicAndMatchesInput(): void
  {
    $bot = new Bot('42:TEST');
    self::assertSame('42:TEST', $bot->token);
  }

  // ── test_equals ─────────────────────────────────────────────────────────────

  /**
   * Upstream: test_equals — bot == Bot("42:TEST"), bot != "42:TEST". In PHP,
   * `==` on objects checks property equality by default; `===` checks identity.
   * The PHP port does not override `__equals`; behavioural equality is
   * expressed through token identity comparison by callers.
   */
  public function testTwoBotInstancesWithSameTokenHaveSameId(): void
  {
    $a = new Bot('42:TEST', session: new MockedSession());
    $b = new Bot('42:TEST', session: new MockedSession());
    self::assertSame($a->getId(), $b->getId());
    self::assertSame($a->token, $b->token);
    self::assertNotSame($a, $b);
  }

  public function testBotDoesNotEqualString(): void
  {
    $bot = new Bot('42:TEST', session: new MockedSession());
    self::assertNotEquals('42:TEST', $bot);
  }

  // ── invalid token ───────────────────────────────────────────────────────────

  /**
   * Complement to test_init_default: confirms the token validator rejects
   * a blank token so downstream URL construction can never inject empty strings.
   */
  public function testInvalidTokenRaisesTokenValidationException(): void
  {
    $this->expectException(TokenValidationException::class);
    new Bot('');
  }

  // ── session dispatch ────────────────────────────────────────────────────────

  /**
   * Upstream: test_emit — Bot::__invoke dispatches to session. Verified via
   * MockedBot/MockedSession without async infrastructure.
   */
  public function testInvokeDispatchesToSession(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(
      GetMe::class,
      ok: true,
      result: new User(id: 42, isBot: true, firstName: 'Test'),
    );
    $result = $bot->getMe();
    self::assertInstanceOf(User::class, $result);
    self::assertSame(42, $result->id);
  }

  // ── middleware invoke chain ─────────────────────────────────────────────────

  /**
   * Supplementary: Bot::__invoke routes through session middleware chain
   * (mirrors upstream's async `await bot(method)` path).
   */
  public function testInvokeRunsThroughMiddlewareChain(): void
  {
    $bot = new MockedBot();
    $log = [];

    $bot->session->middleware->register(new class ($log) extends BaseRequestMiddleware {
      /** @param list<string> $log */
      public function __construct(public array &$log) {}

      public function __invoke(Closure $next, Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
      {
        $this->log[] = 'called';

        return $next($bot, $method, $timeout);
      }
    });

    $bot->addResultFor(
      SendMessage::class,
      ok: true,
      result: new Message(
        messageId: 1,
        date: new DateTime('@0'),
        chat: new Chat(id: 1, type: 'private'),
      ),
    );

    $bot->sendMessage(chatId: 1, text: 'hi');
    self::assertSame(['called'], $log);
  }
}
