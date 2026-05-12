<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types\Shortcuts;

use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Cycle 4 I1 fix: hand-authored shortcut helpers on `User` to surface
 * common derivations (`fullName`, `mentionHtml`, `mentionMarkdown`) the
 * way aiogram's `User` type exposes them. Pre-fix the codegen tree
 * dropped these helpers because they don't map to any
 * `aliases.yml`-driven method call.
 *
 * @internal
 *
 * @coversNothing
 */
final class UserShortcutsTest extends TestCase
{
  public function testFullNameWithBothNames(): void
  {
    $user = new User(id: 42, isBot: false, firstName: 'Ada', lastName: 'Lovelace');
    self::assertSame('Ada Lovelace', $user->fullName());
  }

  public function testFullNameWithFirstNameOnly(): void
  {
    $user = new User(id: 42, isBot: false, firstName: 'Ada');
    self::assertSame('Ada', $user->fullName());
  }

  public function testMentionHtmlEscapesAngleBrackets(): void
  {
    $user = new User(id: 99, isBot: false, firstName: 'Foo<Bar>', lastName: '&Baz');
    $expected = '<a href="tg://user?id=99">Foo&lt;Bar&gt; &amp;Baz</a>';
    self::assertSame($expected, $user->mentionHtml());
  }

  public function testMentionHtmlWithoutLastName(): void
  {
    $user = new User(id: 7, isBot: false, firstName: 'Solo');
    self::assertSame('<a href="tg://user?id=7">Solo</a>', $user->mentionHtml());
  }

  public function testMentionMarkdownPreservesName(): void
  {
    $user = new User(id: 7, isBot: false, firstName: 'Ada', lastName: 'Lovelace');
    self::assertSame('[Ada Lovelace](tg://user?id=7)', $user->mentionMarkdown());
  }

  public function testMentionMarkdownAcceptsCustomLabel(): void
  {
    $user = new User(id: 7, isBot: false, firstName: 'Ada');
    self::assertSame('[Friend](tg://user?id=7)', $user->mentionMarkdown(name: 'Friend'));
  }

  public function testMentionHtmlAcceptsCustomLabel(): void
  {
    $user = new User(id: 7, isBot: false, firstName: 'Ada');
    self::assertSame('<a href="tg://user?id=7">Friend</a>', $user->mentionHtml(name: 'Friend'));
  }
}
