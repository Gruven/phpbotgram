<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types\Shortcuts;

use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\InaccessibleMessage;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Types\ReplyParameters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Cycle 1 review fix: `MessageShortcuts::asReplyParameters` and
 * `InaccessibleMessageShortcuts::asReplyParameters` must mirror upstream
 * aiogram's `Message.as_reply_parameters` signature:
 *
 *     def as_reply_parameters(
 *         self,
 *         allow_sending_without_reply: bool | None = None,
 *         quote: str | None = None,
 *         quote_parse_mode: str | Default | None = Default("parse_mode"),
 *         quote_entities: list[MessageEntity] | None = None,
 *         quote_position: int | None = None,
 *     ) -> ReplyParameters: ...
 *
 * The pre-fix helper was parameterless, hiding the entire quote-formatting
 * surface from PHP callers.
 *
 * @internal
 *
 * @coversNothing
 */
final class AsReplyParametersTest extends TestCase
{
  /**
   * @return list<array{0: class-string, 1: string}>
   */
  public static function targets(): array
  {
    return [
      [Message::class, 'asReplyParameters'],
      [InaccessibleMessage::class, 'asReplyParameters'],
    ];
  }

  /**
   * @param class-string $class
   */
  #[DataProvider('targets')]
  public function testSignatureMirrorsAiogram(string $class, string $method): void
  {
    $r = new ReflectionMethod($class, $method);
    $params = $r->getParameters();

    self::assertCount(5, $params, "{$class}::{$method} must take 5 named optional params");

    [$allow, $quote, $quoteParseMode, $quoteEntities, $quotePosition] = $params;

    self::assertSame('allowSendingWithoutReply', $allow->getName());
    self::assertSame('quote', $quote->getName());
    self::assertSame('quoteParseMode', $quoteParseMode->getName());
    self::assertSame('quoteEntities', $quoteEntities->getName());
    self::assertSame('quotePosition', $quotePosition->getName());

    // Defaults: every param has a default; `quoteParseMode` defaults to a
    // BotDefault sentinel; the rest default to null.
    self::assertTrue($allow->isDefaultValueAvailable(), 'allowSendingWithoutReply must have a default');
    self::assertNull($allow->getDefaultValue());

    self::assertTrue($quote->isDefaultValueAvailable(), 'quote must have a default');
    self::assertNull($quote->getDefaultValue());

    self::assertTrue($quoteParseMode->isDefaultValueAvailable(), 'quoteParseMode must have a default');
    $qpmDefault = $quoteParseMode->getDefaultValue();
    self::assertInstanceOf(BotDefault::class, $qpmDefault);
    self::assertSame('parse_mode', $qpmDefault->name);

    self::assertTrue($quoteEntities->isDefaultValueAvailable(), 'quoteEntities must have a default');
    self::assertNull($quoteEntities->getDefaultValue());

    self::assertTrue($quotePosition->isDefaultValueAvailable(), 'quotePosition must have a default');
    self::assertNull($quotePosition->getDefaultValue());

    // Return type still ReplyParameters.
    $rt = $r->getReturnType();
    self::assertInstanceOf(ReflectionNamedType::class, $rt);
    self::assertSame(ReplyParameters::class, $rt->getName());

    // quoteParseMode must accept BotDefault, string, and null.
    $qpmType = $quoteParseMode->getType();
    self::assertInstanceOf(ReflectionUnionType::class, $qpmType, 'quoteParseMode must be a union type');
    $qpmTypeNames = array_map(static fn($t) => $t instanceof ReflectionNamedType ? $t->getName() : (string)$t, $qpmType->getTypes());

    self::assertContains('null', $qpmTypeNames);
    self::assertContains('string', $qpmTypeNames);
    self::assertContains(BotDefault::class, $qpmTypeNames);
  }

  /**
   * Concrete call against a real Message instance: pass every param and
   * verify the resulting ReplyParameters carries the values through.
   */
  public function testMessageThreadsAllParamsToReplyParameters(): void
  {
    $message = new Message(
      messageId: 7,
      date: new DateTime('@0'),
      chat: new Chat(id: 42, type: 'private'),
    );

    $entity = new MessageEntity(type: 'bold', offset: 0, length: 4);
    $rp = $message->asReplyParameters(
      allowSendingWithoutReply: true,
      quote: 'hello',
      quoteParseMode: 'MarkdownV2',
      quoteEntities: [$entity],
      quotePosition: 3,
    );

    self::assertInstanceOf(ReplyParameters::class, $rp);
    self::assertSame(7, $rp->messageId);
    self::assertSame(42, $rp->chatId);
    self::assertTrue($rp->allowSendingWithoutReply);
    self::assertSame('hello', $rp->quote);
    self::assertSame('MarkdownV2', $rp->quoteParseMode);
    self::assertSame([$entity], $rp->quoteEntities);
    self::assertSame(3, $rp->quotePosition);
  }

  /**
   * Default-only invocation: produce a ReplyParameters pinned to the
   * message's IDs with `quoteParseMode` resolved to null (no bound bot
   * means no default-properties resolution; the server-side default
   * applies). The aiogram-style `Default("parse_mode")` sentinel is
   * resolved inside the trait because `ReplyParameters::$quoteParseMode`
   * is typed `?string` and PHP rejects assigning a BotDefault to it.
   */
  public function testMessageDefaultsResolveBotDefaultToNullWithoutBot(): void
  {
    $message = new Message(
      messageId: 7,
      date: new DateTime('@0'),
      chat: new Chat(id: 42, type: 'private'),
    );

    $rp = $message->asReplyParameters();

    self::assertSame(7, $rp->messageId);
    self::assertSame(42, $rp->chatId);
    self::assertNull($rp->allowSendingWithoutReply);
    self::assertNull($rp->quote);
    self::assertNull($rp->quoteEntities);
    self::assertNull($rp->quotePosition);
    // No bot bound on this Message → BotDefault('parse_mode') resolves to null.
    self::assertNull($rp->quoteParseMode);
  }
}
