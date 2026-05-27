<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\Chat;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_types/test_chat.py
 *
 * Upstream skips:
 *   - Pydantic model_validate / model_dump — API divergence (a): handled by
 *     Session::prepareValue() + Serializer::unpack(), covered in
 *     BaseSessionTest and SerializerTest.
 *   - test_parse_chat_id (Python-specific parse helper) — API divergence (a):
 *     not ported; callers use the raw `id` int property directly.
 *   - full_name / effective_name computed properties — API divergence (a): PHP
 *     does not expose computed name shortcuts on Chat (only UserShortcuts on User).
 *
 * @internal
 */
final class ChatTypeTest extends TestCase
{
  // ── construction ─────────────────────────────────────────────────────────────

  public function testPrivateChatRequiredOnly(): void
  {
    $chat = new Chat(id: 1, type: 'private');
    self::assertSame(1, $chat->id);
    self::assertSame('private', $chat->type);
    self::assertNull($chat->title);
    self::assertNull($chat->username);
    self::assertNull($chat->firstName);
    self::assertNull($chat->lastName);
  }

  public function testGroupChat(): void
  {
    $chat = new Chat(id: -100, type: 'group', title: 'My Group');
    self::assertSame(-100, $chat->id);
    self::assertSame('group', $chat->type);
    self::assertSame('My Group', $chat->title);
  }

  public function testSupergroupWithUsername(): void
  {
    $chat = new Chat(id: -1001234567, type: 'supergroup', title: 'Super', username: 'supergroup_un');
    self::assertSame('supergroup', $chat->type);
    self::assertSame('supergroup_un', $chat->username);
  }

  public function testChannelWithForum(): void
  {
    $chat = new Chat(id: -1009999999, type: 'channel', title: 'Forum Channel', isForum: true);
    self::assertSame('channel', $chat->type);
    self::assertTrue($chat->isForum);
  }

  public function testPrivateChatWithFirstLastName(): void
  {
    $chat = new Chat(id: 5, type: 'private', firstName: 'Bob', lastName: 'Smith');
    self::assertSame('Bob', $chat->firstName);
    self::assertSame('Smith', $chat->lastName);
  }
}
