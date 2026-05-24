<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Methods\AnswerCallbackQuery;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_types/test_callback_query.py
 *
 * Upstream skips:
 *   - Pydantic model_validate / model_dump — API divergence (a).
 *   - de_json helper — API divergence (a).
 *   - async answer() shortcut test (coroutine-based) — API divergence (a):
 *     PHP's answer() returns an AnswerCallbackQuery method object that the
 *     caller dispatches; tested below via MockedBot.
 *
 * @internal
 */
final class CallbackQueryTypeTest extends TestCase
{
  // ── construction ─────────────────────────────────────────────────────────────

  public function testRequiredArgsOnly(): void
  {
    $user = new User(id: 1, isBot: false, firstName: 'Alice');
    $cq = new CallbackQuery(id: 'cq_abc', fromUser: $user, chatInstance: 'ci1');
    self::assertSame('cq_abc', $cq->id);
    self::assertSame($user, $cq->fromUser);
    self::assertSame('ci1', $cq->chatInstance);
    self::assertNull($cq->data);
    self::assertNull($cq->message);
    self::assertNull($cq->inlineMessageId);
    self::assertNull($cq->gameShortName);
  }

  public function testWithData(): void
  {
    $user = new User(id: 2, isBot: false, firstName: 'Bob');
    $cq = new CallbackQuery(id: 'cq1', fromUser: $user, chatInstance: 'ci', data: 'action_42');
    self::assertSame('action_42', $cq->data);
  }

  public function testWithMessage(): void
  {
    $user = new User(id: 3, isBot: false, firstName: 'Carol');
    $msg = new Message(messageId: 10, date: new DateTime('@0'), chat: new Chat(id: 1, type: 'private'));
    $cq = new CallbackQuery(id: 'cq2', fromUser: $user, chatInstance: 'ci', message: $msg, data: 'btn');
    self::assertInstanceOf(Message::class, $cq->message);
    self::assertSame(10, $cq->message->messageId);
  }

  public function testWithInlineMessageId(): void
  {
    $user = new User(id: 4, isBot: false, firstName: 'Dave');
    $cq = new CallbackQuery(id: 'cq3', fromUser: $user, chatInstance: 'ci', inlineMessageId: 'inline_xyz');
    self::assertSame('inline_xyz', $cq->inlineMessageId);
  }

  // ── shortcut: answer() ───────────────────────────────────────────────────────

  public function testAnswerShortcutBuildsMethod(): void
  {
    $user = new User(id: 1, isBot: false, firstName: 'A');
    $cq = new CallbackQuery(id: 'cq_x', fromUser: $user, chatInstance: 'ci');

    $method = $cq->answer(text: 'Done', showAlert: true);

    self::assertInstanceOf(AnswerCallbackQuery::class, $method);
    self::assertSame('cq_x', $method->callbackQueryId);
    self::assertSame('Done', $method->text);
    self::assertTrue($method->showAlert);
  }

  public function testAnswerShortcutRoundTripViaBot(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(AnswerCallbackQuery::class, ok: true, result: true);

    $user = new User(id: 1, isBot: false, firstName: 'A');
    $cq = new CallbackQuery(id: 'cq_bot', fromUser: $user, chatInstance: 'ci');

    // Bind bot to the shortcut method and emit it.
    $result = $bot($cq->answer(text: 'OK'));

    self::assertTrue($result);
  }
}
