<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Methods;

use Gruven\PhpBotGram\Methods\StopPoll;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Poll;
use Gruven\PhpBotGram\Types\PollOption;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_methods/test_stop_poll.py
 *
 * Upstream skips:
 *   - async infrastructure — API divergence (a)/test infrastructure divergence (c).
 *   - Pydantic model_dump for Poll — API divergence (a).
 *
 * @internal
 */
final class StopPollTest extends TestCase
{
  // ── constructor shape ────────────────────────────────────────────────────────

  public function testRequiredArgs(): void
  {
    $method = new StopPoll(chatId: 10, messageId: 20);
    self::assertSame(10, $method->chatId);
    self::assertSame(20, $method->messageId);
    self::assertNull($method->businessConnectionId);
    self::assertNull($method->replyMarkup);
  }

  public function testApiMethodConstant(): void
  {
    self::assertSame('stopPoll', StopPoll::ApiMethod);
  }

  public function testReturnsTypePoll(): void
  {
    self::assertSame(Poll::class, StopPoll::ReturnsType);
  }

  // ── MockedBot round-trip ─────────────────────────────────────────────────────

  public function testRoundTripReturnsPoll(): void
  {
    $bot = new MockedBot();
    $poll = new Poll(
      id: 'poll_abc',
      question: 'Is PHP great?',
      options: [
        new PollOption(persistentId: 'opt1', text: 'Yes', voterCount: 42),
        new PollOption(persistentId: 'opt2', text: 'Absolutely', voterCount: 7),
      ],
      totalVoterCount: 49,
      isClosed: true,
      isAnonymous: true,
      type: 'regular',
      allowsMultipleAnswers: false,
      allowsRevoting: false,
      membersOnly: false,
    );
    $bot->addResultFor(StopPoll::class, ok: true, result: $poll);

    $result = $bot->stopPoll(chatId: 10, messageId: 20);

    self::assertInstanceOf(Poll::class, $result);
    self::assertSame('poll_abc', $result->id);
    self::assertTrue($result->isClosed);
    self::assertCount(2, $result->options);
  }

  public function testGetRequestCapturesChatAndMessageId(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(StopPoll::class, ok: true, result: new Poll(
      id: 'p',
      question: 'Q?',
      options: [],
      totalVoterCount: 0,
      isClosed: true,
      isAnonymous: true,
      type: 'regular',
      allowsMultipleAnswers: false,
      allowsRevoting: false,
      membersOnly: false,
    ));

    $bot->stopPoll(chatId: 55, messageId: 77);

    $sent = $bot->getRequest();
    self::assertInstanceOf(StopPoll::class, $sent);
    self::assertSame(55, $sent->chatId);
    self::assertSame(77, $sent->messageId);
  }
}
