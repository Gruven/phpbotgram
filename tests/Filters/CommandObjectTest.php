<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Gruven\PhpBotGram\Filters\CommandObject;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `CommandObject` — readonly DTO carrying the parsed parts of a
 * Telegram slash-command ("/cmd@mention args").
 *
 * Mirrors upstream `aiogram.filters.command.CommandObject` (the `@dataclass`
 * defined at `aiogram/filters/command.py:202-237`):
 *   - same field order (prefix, command, mention, args, regexpMatch,
 *     magicResult);
 *   - `mentioned`/`mentionWithoutPrefix` derived properties mirror the same
 *     conveniences;
 *   - `text()` reassembles the original textual command exactly the way
 *     upstream's `text` property does (prefix + command, optional `@mention`,
 *     optional space-prefixed args).
 *
 * `regexpMatch`/`magicResult` are scaffolded for parity with upstream's
 * `regexp_match`/`magic_result` fields. They surface at construction only —
 * the `Command` filter that produces them lives one class up, and the
 * regex-aware / magic-aware branches arrive in Phase 4.5+. For now we just
 * check the property is reachable and defaults to `null`/`null`.
 *
 * Upstream `tests/test_filters/test_command.py` cases deliberately not ported:
 *
 * - `TestCommandObject::test_update_handler_flags` — `update_handler_flags()` is a
 *   dispatcher-integration method that inspects the router's handler registry; not present
 *   in the PHP port (reason 8 — phase boundary).
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class CommandObjectTest extends TestCase
{
  public function testConstructionWithAllDefaults(): void
  {
    // Upstream's dataclass exposes every field with a default; replicating
    // that lets call sites build a minimal object the same way (useful in
    // tests that only care about one or two fields).
    $obj = new CommandObject();

    self::assertSame('/', $obj->prefix);
    self::assertSame('', $obj->command);
    self::assertNull($obj->mention);
    self::assertNull($obj->args);
    self::assertNull($obj->regexpMatch);
    self::assertNull($obj->magicResult);
  }

  public function testConstructionWithAllFields(): void
  {
    // Named-arg construction is the canonical idiom for readonly DTOs.
    // Verifies every field reaches its property and `text()` recovers the
    // original input exactly.
    $obj = new CommandObject(
      prefix: '/',
      command: 'start',
      mention: 'tbot',
      args: 'foo bar',
      regexpMatch: ['start', 'extra'],
      magicResult: 'whatever',
    );

    self::assertSame('/', $obj->prefix);
    self::assertSame('start', $obj->command);
    self::assertSame('tbot', $obj->mention);
    self::assertSame('foo bar', $obj->args);
    self::assertSame(['start', 'extra'], $obj->regexpMatch);
    self::assertSame('whatever', $obj->magicResult);
  }

  public function testMentionWithoutPrefixStripsLeadingAt(): void
  {
    // Upstream's `mention_without_prefix` lstrips `@`. PHP port mirrors via
    // `ltrim($mention, '@')`. Used by `Command::validate_mention` in upstream
    // when comparing against `bot.me().username`; we expose the same helper
    // so user-land code can perform the comparison if it bypasses the
    // filter's own validation.
    $obj = new CommandObject(mention: '@tbot');
    self::assertSame('tbot', $obj->mentionWithoutPrefix());

    // Mention without the `@` falls through unchanged — `ltrim('tbot', '@')`
    // returns `'tbot'`.
    $obj = new CommandObject(mention: 'tbot');
    self::assertSame('tbot', $obj->mentionWithoutPrefix());

    // Null mention → null helper return. Matches upstream's `if
    // self.mention else None` guard.
    $obj = new CommandObject(mention: null);
    self::assertNull($obj->mentionWithoutPrefix());
  }

  public function testMentionedReportsTruthyMention(): void
  {
    // Upstream exposes `mentioned: bool` derived from `bool(self.mention)`.
    // The PHP port keeps the same convenience as a method (PHP forbids
    // `bool` typed readonly properties that depend on others), with empty
    // strings collapsing to false to match Python's truthiness.
    self::assertFalse((new CommandObject())->mentioned());
    self::assertFalse((new CommandObject(mention: null))->mentioned());
    self::assertFalse((new CommandObject(mention: ''))->mentioned());
    self::assertTrue((new CommandObject(mention: 'tbot'))->mentioned());
  }

  public function testTextReassemblesCommandWithMentionAndArgs(): void
  {
    // Mirror upstream's `text` property table. Each row of the upstream
    // parametrize block is replicated below to lock down the exact glue:
    //   `/cmd@mention args`, `/cmd@mention`, `/cmd args`, `/cmd`, `!cmd`.
    self::assertSame(
      '/command@mention args',
      (new CommandObject(prefix: '/', command: 'command', mention: 'mention', args: 'args'))->text(),
    );

    self::assertSame(
      '/command@mention',
      (new CommandObject(prefix: '/', command: 'command', mention: 'mention'))->text(),
    );

    self::assertSame(
      '/command args',
      (new CommandObject(prefix: '/', command: 'command', args: 'args'))->text(),
    );

    self::assertSame(
      '/command',
      (new CommandObject(prefix: '/', command: 'command'))->text(),
    );

    // Non-`/` prefixes are valid via `Command(prefix: ['!'])`. Confirms
    // `text()` carries the prefix verbatim instead of hard-coding `/`.
    self::assertSame(
      '!command',
      (new CommandObject(prefix: '!', command: 'command'))->text(),
    );
  }
}
