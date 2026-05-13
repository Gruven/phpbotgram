<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Filters\CommandObject;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `Command` — port of `aiogram.filters.command.Command`. Matches
 * `tests/test_filters/test_command.py` row-by-row where the upstream rows
 * cover features supported by Task 4.7 (strings only — regex / magic / deep
 * link variations live in later tasks but get scaffolded here).
 *
 * Spec deviation acknowledged inline: PHP forbids parameters after a
 * variadic, so the constructor takes `string|list<string> $commands`
 * positionally and exposes `Command::of(...$cmds)` as a variadic-friendly
 * factory. See § "Filters in detail" / "Command" in the design doc.
 */
final class CommandTest extends TestCase
{
  public function testIsAFilterSubclass(): void
  {
    // Smoke-check the inheritance: dispatcher cascading + Logic combinators
    // rely on every concrete filter being a `Filter`. Locking this in
    // protects against accidental refactors that break that contract.
    self::assertInstanceOf(Filter::class, Command::of('start'));
  }

  public function testConstructorThrowsOnEmptyCommandList(): void
  {
    // Upstream raises `ValueError('At least one command should be specified')`
    // for `Command()` (no commands). PHP equivalent is
    // `InvalidArgumentException` so call sites don't need to catch a
    // ValueError-imitation type.
    $this->expectException(InvalidArgumentException::class);
    new Command([]);
  }

  public function testOfFactoryStoresCommandsInDeclarationOrder(): void
  {
    // `Command::of('start', 'help')` is the ergonomic shorthand; equivalent
    // to upstream `Command('start', 'help')`. The readonly `$commands` list
    // must preserve declaration order so subsequent matching iterates in
    // registration order (relevant when distinct patterns could both match).
    $filter = Command::of('start', 'help');

    self::assertSame(['start', 'help'], $filter->commands);
    self::assertSame(['/'], $filter->prefix);
    self::assertFalse($filter->ignoreCase);
    self::assertFalse($filter->ignoreMention);
  }

  public function testArrayConstructorAcceptsListOfCommands(): void
  {
    // The PHP variadic-position constraint forces the positional first arg
    // to be `string|list<string>`. Lock down the array form.
    $filter = new Command(['start', 'help']);

    self::assertSame(['start', 'help'], $filter->commands);
  }

  public function testArrayConstructorAcceptsSingleString(): void
  {
    // Upstream accepts `Command(commands="start")` (single string),
    // normalised to `("start",)`. The PHP port mirrors via
    // `string|list<string>` first arg.
    $filter = new Command('start');

    self::assertSame(['start'], $filter->commands);
  }

  public function testRejectsNonMessageEvents(): void
  {
    // Upstream `__call__` opens with `if not isinstance(message, Message):
    // return False`. Mirror that defensively — the dispatcher filter
    // pipeline routes by event type so this branch fires only when a
    // misconfigured user wires `Command` onto a non-message observer.
    $filter = Command::of('start');

    self::assertFalse($filter(new Update(updateId: 1)));
  }

  public function testReturnsFalseForEmptyTextAndCaption(): void
  {
    // Mirror upstream's `text = message.text or message.caption; if not
    // text: return False`. We test the three early-exit branches: text
    // null + caption null, text empty string, caption empty string.
    $filter = Command::of('start');

    self::assertFalse($filter($this->message(text: null)));
    self::assertFalse($filter($this->message(text: '')));
    self::assertFalse($filter($this->message(text: null, caption: '')));
  }

  public function testMatchesSimpleCommand(): void
  {
    // `/start` against `Command::of('start')` → match. Returned array is
    // exactly `['command' => CommandObject]`; upstream's `result =
    // {"command": command}` mapping. Verify the CommandObject carries the
    // parsed pieces (prefix, command, mention=null, args=null).
    $filter = Command::of('start');

    $command = $this->matchCommand($filter, '/start');

    self::assertSame('/', $command->prefix);
    self::assertSame('start', $command->command);
    self::assertNull($command->mention);
    self::assertNull($command->args);
  }

  public function testDoesNotMatchDifferentCommand(): void
  {
    // `/help` against `Command::of('start')` → no match. Upstream raises
    // `CommandException('Command did not match pattern')` and the
    // `__call__` wrapper returns False; PHP port returns false directly
    // from `parseCommand`.
    $filter = Command::of('start');

    self::assertFalse($filter($this->message(text: '/help')));
  }

  public function testMatchesCommandWithArgs(): void
  {
    // `/start hello world` → match; CommandObject.args carries the full
    // post-command remainder verbatim. Upstream splits via
    // `text.split(maxsplit=1)`, exposing only the tail; the PHP port
    // mirrors with `preg_split('/\s+/', $rest, 2)` and stores `$parts[1]`
    // as `args`.
    $command = $this->matchCommand(Command::of('start'), '/start hello world');

    self::assertSame('start', $command->command);
    self::assertSame('hello world', $command->args);
  }

  public function testMatchesCommandWithExtraInternalSpaces(): void
  {
    // Upstream `text.split(maxsplit=1)` consumes the FIRST run of
    // whitespace then returns the tail unmodified — extra spaces inside
    // the tail are preserved. We mirror that with `preg_split('/\s+/',
    // $rest, 2)`. Lock the contract: `/start   hello   world` → args
    // is `'hello   world'`, NOT `'hello world'`.
    $command = $this->matchCommand(Command::of('start'), '/start   hello   world');

    self::assertSame('hello   world', $command->args);
  }

  public function testMatchesCommandWithMention(): void
  {
    // MockedBot's stub username is `tbot`. `/start@tbot` against that bot
    // matches: upstream `validate_mention` compares `command.mention.lower()
    // != me.username.lower()` and raises when they differ; matching means
    // no exception. PHP port mirrors via `strcasecmp`.
    $command = $this->matchCommand(Command::of('start'), '/start@tbot', new MockedBot());

    self::assertSame('start', $command->command);
    self::assertSame('tbot', $command->mention);
  }

  public function testRejectsMentionForDifferentBot(): void
  {
    // `/start@otherbot` against a bot with username `tbot` → false. The
    // mention check is the one place the bot reference matters; without
    // it (and without `ignoreMention=true`) we'd silently dispatch
    // commands meant for another bot.
    $filter = Command::of('start');
    $bot = new MockedBot();

    self::assertFalse($filter($this->message(text: '/start@otherbot'), ['bot' => $bot]));
  }

  public function testIgnoreMentionSkipsBotUsernameCheck(): void
  {
    // `ignoreMention: true` short-circuits the mention validation so a
    // multi-bot deployment can use a single Command filter for any
    // mention. Lock the contract: even an explicit `@otherbot` matches.
    $filter = new Command('start', ignoreMention: true);
    $command = $this->matchCommand($filter, '/start@otherbot', new MockedBot());

    self::assertSame('otherbot', $command->mention);
  }

  public function testIgnoreCaseFolding(): void
  {
    // Upstream casefolds the registered string commands when
    // `ignore_case=True` so `/TeSt` matches `Command('test',
    // ignore_case=True)`. The PHP port uses `strcasecmp` at match-time
    // instead of pre-casefolding (functionally identical for ASCII
    // commands; the upstream parametrize block only tests ASCII).
    $filter = new Command('test', ignoreCase: true);

    self::assertIsArray($filter($this->message(text: '/TeSt')));
    self::assertIsArray($filter($this->message(text: '/test')));
    self::assertIsArray($filter($this->message(text: '/TEST')));
  }

  public function testIgnoreCaseFalseRejectsMixedCase(): void
  {
    // Negative path for the row above: without `ignoreCase`, `/TeSt`
    // does NOT match `Command::of('test')`. Mirrors upstream's strict-
    // comparison branch.
    $filter = Command::of('test');

    self::assertFalse($filter($this->message(text: '/TeSt')));
  }

  public function testMultiplePrefixes(): void
  {
    // Upstream accepts `prefix: "/!"` (a single string treated as a set of
    // chars). The PHP port takes a `list<string>` so prefixes can be
    // multi-char ("/" + "!cmd " or even "$bang" if desired); for parity
    // with upstream's `"/!"` we pass `['/', '!']`.
    $filter = new Command('start', prefix: ['/', '!']);

    self::assertIsArray($filter($this->message(text: '/start')));
    self::assertIsArray($filter($this->message(text: '!start')));
    self::assertFalse($filter($this->message(text: '?start')));
  }

  public function testMultipleCommandsMatchEither(): void
  {
    // Multiple registered patterns: any match accepts. Upstream loops over
    // `self.commands` and returns the first match.
    $filter = Command::of('start', 'help');

    self::assertSame('start', $this->matchCommand($filter, '/start')->command);
    self::assertSame('help', $this->matchCommand($filter, '/help')->command);
    self::assertFalse($filter($this->message(text: '/about')));
  }

  public function testWithoutBotInKwargsSkipsMentionCheck(): void
  {
    // No `bot` kwarg → mention validation is unreachable; the filter
    // falls through to the registered-command comparison. This matters
    // for unit-test scaffolding where users may invoke `Command` without
    // a bot, but should NOT be relied on in production (the dispatcher
    // always injects `bot`). The PHP port treats a missing bot as
    // "skip mention check" rather than as a hard reject.
    $command = $this->matchCommand(Command::of('start'), '/start@anybot');

    self::assertSame('start', $command->command);
    self::assertSame('anybot', $command->mention);
  }

  public function testMatchesAgainstCaptionWhenTextIsNull(): void
  {
    // Upstream's `text = message.text or message.caption` falls back to
    // caption when text is None. Media messages carry their command in
    // the caption — locking this preserves the documented `/cmd in
    // caption` ergonomic.
    $filter = Command::of('start');

    $result = $filter($this->message(text: null, caption: '/start now'));
    self::assertIsArray($result);
    self::assertArrayHasKey('command', $result);

    $command = $result['command'];
    self::assertInstanceOf(CommandObject::class, $command);
    self::assertSame('start', $command->command);
    self::assertSame('now', $command->args);
  }

  public function testRejectsNoPrefixMatch(): void
  {
    // `test` (no prefix) against any `Command::of('test')` → false.
    // Upstream `validate_prefix` raises `CommandException('Invalid command
    // prefix')`; PHP port returns null from `parseCommand` which collapses
    // to `false` at the call site.
    $filter = Command::of('test');

    self::assertFalse($filter($this->message(text: 'test')));
    self::assertFalse($filter($this->message(text: ' test'))); // leading space, no prefix
  }

  public function testRejectsBarePrefix(): void
  {
    // `/` alone (no command name) and `/ test` (prefix followed by a space
    // before any command name) are upstream-tested edge cases that must
    // not match.
    $filter = Command::of('test');

    self::assertFalse($filter($this->message(text: '/')));
    self::assertFalse($filter($this->message(text: '/ test')));
  }

  public function testEmptyMentionIsNotPropagated(): void
  {
    // Upstream issue #1013 fix: `'/test'` (no `@`) parses to
    // `mention=None`, not `mention=''`. Lock the same nuance in the PHP
    // port — `str_contains($cmd, '@')` is false so the explode branch is
    // skipped; `$mention` stays null.
    self::assertNull($this->matchCommand(Command::of('test'), '/test')->mention);
  }

  /**
   * Build a minimal Message for filter tests.
   */
  private function message(?string $text = null, ?string $caption = null): Message
  {
    return new Message(
      messageId: 1,
      date: new DateTime('2024-01-01'),
      chat: new Chat(id: 1, type: 'private'),
      text: $text,
      caption: $caption,
    );
  }

  /**
   * Helper that runs the filter and unwraps the expected `['command' =>
   * CommandObject]` accept-shape. Asserts the filter produced an array,
   * carried the `command` key, and that key holds a `CommandObject` — then
   * returns it for further per-test assertions. Centralises the
   * type-narrowing dance so individual cases stay short and PHPStan sees
   * the concrete type.
   */
  private function matchCommand(Command $filter, string $text, ?MockedBot $bot = null): CommandObject
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
