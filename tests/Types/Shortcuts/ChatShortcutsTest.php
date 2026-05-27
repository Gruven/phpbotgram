<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types\Shortcuts;

use Gruven\PhpBotGram\Types\Chat;
use PHPUnit\Framework\TestCase;

/**
 * Cycle 4 I1 fix: hand-authored `Chat::fullName()` helper. Mirrors
 * aiogram's `Chat.full_name` accessor: private chats concat
 * `firstName` + `lastName`; everything else (`group`, `supergroup`,
 * `channel`, `direct_messages`) falls back to `title`.
 *
 * @internal
 *
 * @coversNothing
 */
final class ChatShortcutsTest extends TestCase
{
  public function testFullNameForPrivateChatConcatsNames(): void
  {
    $chat = new Chat(id: 1, type: 'private', firstName: 'Ada', lastName: 'Lovelace');
    self::assertSame('Ada Lovelace', $chat->fullName());
  }

  public function testFullNameForPrivateChatWithFirstNameOnly(): void
  {
    $chat = new Chat(id: 1, type: 'private', firstName: 'Ada');
    self::assertSame('Ada', $chat->fullName());
  }

  public function testFullNameForGroupFallsBackToTitle(): void
  {
    $chat = new Chat(id: -1, type: 'group', title: 'Coders');
    self::assertSame('Coders', $chat->fullName());
  }

  public function testFullNameForSupergroupFallsBackToTitle(): void
  {
    $chat = new Chat(id: -1001, type: 'supergroup', title: 'Coders HQ');
    self::assertSame('Coders HQ', $chat->fullName());
  }

  public function testFullNameForChannelFallsBackToTitle(): void
  {
    $chat = new Chat(id: -100, type: 'channel', title: 'News');
    self::assertSame('News', $chat->fullName());
  }

  public function testFullNameForPrivateChatWithoutNamesReturnsEmpty(): void
  {
    $chat = new Chat(id: 1, type: 'private');
    self::assertSame('', $chat->fullName());
  }
}
