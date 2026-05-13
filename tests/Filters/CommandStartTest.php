<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Gruven\PhpBotGram\Filters\CommandObject;
use Gruven\PhpBotGram\Filters\CommandStart;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `CommandStart` — convenience subclass that pins the command
 * name to `start` and adds `deep_link` semantics. Port of
 * `aiogram.filters.command.CommandStart` (`aiogram/filters/command.py:240-303`).
 *
 * Spec deviation: Task 4.7 does not implement `deep_link_encoded` (Base64
 * payload decoding via `aiogram.utils.deep_linking.decode_payload`); that
 * lands in a later task alongside the deep-linking utility port. The
 * `deepLinkEncoded` flag is therefore not exposed yet. See § "Filters in
 * detail" / `CommandStart` for the full signature.
 */
final class CommandStartTest extends TestCase
{
  public function testIsAFilterSubclass(): void
  {
    // Same smoke-check as `Command`: dispatcher cascading + Logic combinators
    // rely on every concrete filter being a `Filter` subclass.
    self::assertInstanceOf(Filter::class, new CommandStart());
  }

  public function testBasicStartMatches(): void
  {
    // `/start` against `new CommandStart()` (no deep link flag) → match.
    // Upstream parametrize row: `["/start", CommandStart(), True]`.
    $filter = new CommandStart();

    $result = $filter($this->message(text: '/start'));

    self::assertIsArray($result);
    self::assertInstanceOf(CommandObject::class, $result['command']);
    self::assertSame('start', $result['command']->command);
    self::assertNull($result['command']->args);
  }

  public function testStartWithArgsMatchesWithoutDeepLinkFlag(): void
  {
    // With `deepLink` unset (null), args are tolerated — the filter only
    // gates on args presence/absence when `deepLink` is explicitly set.
    // Upstream row: `["/start test", CommandStart(), True]`.
    $command = $this->matchStart(new CommandStart(), '/start test');

    self::assertSame('test', $command->args);
  }

  public function testDeepLinkTrueRequiresArgs(): void
  {
    // `deepLink: true` → args MUST be present. `/start` alone (no payload)
    // → false. Upstream rows:
    //   ["/start", CommandStart(deep_link=True), False],
    //   ["/start test", CommandStart(deep_link=True), True],
    $filter = new CommandStart(deepLink: true);

    self::assertFalse($filter($this->message(text: '/start')));
    self::assertSame('test', $this->matchStart($filter, '/start test')->args);
  }

  public function testDeepLinkFalseRejectsArgs(): void
  {
    // `deepLink: false` → args MUST be absent. `/start test` (payload
    // present) → false. Upstream rows:
    //   ["/start", CommandStart(deep_link=False), True],
    //   ["/start test", CommandStart(deep_link=False), False],
    $filter = new CommandStart(deepLink: false);

    self::assertIsArray($filter($this->message(text: '/start')));
    self::assertFalse($filter($this->message(text: '/start test')));
  }

  public function testIgnoreCaseAndMentionForwardToInnerCommand(): void
  {
    // `CommandStart` delegates to a nested `Command('start', …)`. The
    // `ignoreCase` and `ignoreMention` flags pass through unchanged.
    // Lock that ergonomic so users get the same flags as `Command` without
    // having to wire them themselves.
    $filter = new CommandStart(ignoreCase: true, ignoreMention: true);
    $bot = new MockedBot();

    self::assertIsArray($filter($this->message(text: '/Start')));
    self::assertIsArray($filter($this->message(text: '/start@anybot'), ['bot' => $bot]));
  }

  public function testNonStartCommandRejected(): void
  {
    // `/help` against CommandStart → false; the inner Command is locked to
    // the literal command `start`.
    $filter = new CommandStart();

    self::assertFalse($filter($this->message(text: '/help')));
  }

  /**
   * Build a minimal Message for filter tests.
   */
  private function message(?string $text = null): Message
  {
    return new Message(
      messageId: 1,
      date: new DateTime('2024-01-01'),
      chat: new Chat(id: 1, type: 'private'),
      text: $text,
    );
  }

  /**
   * Run the filter and unwrap the expected `['command' => CommandObject]`
   * accept-shape so individual tests can stay concise while PHPStan sees
   * the concrete type. Companion to `CommandTest::matchCommand`.
   */
  private function matchStart(CommandStart $filter, string $text, ?MockedBot $bot = null): CommandObject
  {
    $kwargs = $bot !== null ? ['bot' => $bot] : [];
    $result = $filter($this->message(text: $text), $kwargs);

    self::assertIsArray($result);
    self::assertArrayHasKey('command', $result);

    $command = $result['command'];
    self::assertInstanceOf(CommandObject::class, $command);

    return $command;
  }
}
