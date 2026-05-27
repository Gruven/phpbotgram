<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_types/test_user.py
 *
 * Upstream skips:
 *   - Pydantic model_validate / model_dump round-trips — API divergence (a):
 *     PHP DTOs are plain final classes; serialization is handled by
 *     Session::prepareValue() + Serializer::unpack(), covered in
 *     BaseSessionTest and SerializerTest.
 *   - de_json (async JSON parsing) — API divergence (a): handled by
 *     Session::jsonLoads.
 *   - test_full_name (computed Python property) — API divergence (a):
 *     PHP exposes firstName/lastName as separate properties; callers compute
 *     concatenation themselves.
 *
 * @internal
 */
final class UserTypeTest extends TestCase
{
  // ── construction ─────────────────────────────────────────────────────────────

  public function testRequiredArgsOnly(): void
  {
    $user = new User(id: 1, isBot: false, firstName: 'Alice');
    self::assertSame(1, $user->id);
    self::assertFalse($user->isBot);
    self::assertSame('Alice', $user->firstName);
    self::assertNull($user->lastName);
    self::assertNull($user->username);
    self::assertNull($user->languageCode);
  }

  public function testFullUser(): void
  {
    $user = new User(
      id: 42,
      isBot: false,
      firstName: 'John',
      lastName: 'Doe',
      username: 'johndoe',
      languageCode: 'en',
      isPremium: true,
    );
    self::assertSame(42, $user->id);
    self::assertSame('John', $user->firstName);
    self::assertSame('Doe', $user->lastName);
    self::assertSame('johndoe', $user->username);
    self::assertSame('en', $user->languageCode);
    self::assertTrue($user->isPremium);
  }

  public function testBotUser(): void
  {
    $bot = new User(id: 100, isBot: true, firstName: 'MyBot', username: 'mybot');
    self::assertTrue($bot->isBot);
    self::assertSame('mybot', $bot->username);
  }

  public function testAllOptionalsFalseAndNull(): void
  {
    $user = new User(id: 1, isBot: false, firstName: 'A');
    self::assertNull($user->isPremium);
    self::assertNull($user->addedToAttachmentMenu);
    self::assertNull($user->canJoinGroups);
    self::assertNull($user->canReadAllGroupMessages);
    self::assertNull($user->supportsInlineQueries);
    self::assertNull($user->canConnectToBusiness);
    self::assertNull($user->hasMainWebApp);
  }
}
