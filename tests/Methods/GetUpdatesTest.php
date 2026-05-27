<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Methods;

use Gruven\PhpBotGram\Methods\GetUpdates;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_methods/test_get_updates.py
 *
 * Upstream skips:
 *   - async polling loop tests — API divergence (a): polling loop is covered by
 *     tests/Dispatcher/PollingTest.php.
 *   - Pydantic model_validate for Update list — API divergence (a): the
 *     `list:Update` return type is handled by Session::checkResponse(); covered
 *     by BaseSessionTest.
 *
 * @internal
 */
final class GetUpdatesTest extends TestCase
{
  // ── constructor shape ────────────────────────────────────────────────────────

  public function testDefaultsAllNull(): void
  {
    $method = new GetUpdates();
    self::assertNull($method->offset);
    self::assertNull($method->limit);
    self::assertNull($method->timeout);
    self::assertNull($method->allowedUpdates);
  }

  public function testWithAllParams(): void
  {
    $method = new GetUpdates(offset: 100, limit: 50, timeout: 30, allowedUpdates: ['message', 'callback_query']);
    self::assertSame(100, $method->offset);
    self::assertSame(50, $method->limit);
    self::assertSame(30, $method->timeout);
    self::assertSame(['message', 'callback_query'], $method->allowedUpdates);
  }

  public function testApiMethodConstant(): void
  {
    self::assertSame('getUpdates', GetUpdates::ApiMethod);
  }

  public function testReturnsTypeIsListUpdate(): void
  {
    self::assertSame('list:Update', GetUpdates::ReturnsType);
  }

  // ── MockedBot round-trip ─────────────────────────────────────────────────────

  public function testRoundTripReturnsUpdateList(): void
  {
    $bot = new MockedBot();
    $update = new Update(
      updateId: 1,
      message: new Message(
        messageId: 10,
        date: new DateTime('@0'),
        chat: new Chat(id: 1, type: 'private'),
        text: 'test',
      ),
    );
    // list:Update — the canned result is a plain PHP array; MockedBot's
    // type check is skipped for non-class ReturnsType strings.
    $bot->addResultFor(GetUpdates::class, ok: true, result: [$update]);

    /** @var list<Update> $results */
    $results = $bot->getUpdates(offset: 0, limit: 1);
    self::assertIsArray($results);
    self::assertCount(1, $results);
    self::assertSame(1, $results[0]->updateId);
  }

  public function testGetRequestCapturesOffset(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(GetUpdates::class, ok: true, result: []);

    $bot->getUpdates(offset: 42, limit: 10);

    $sent = $bot->getRequest();
    self::assertInstanceOf(GetUpdates::class, $sent);
    self::assertSame(42, $sent->offset);
    self::assertSame(10, $sent->limit);
  }
}
