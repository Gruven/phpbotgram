<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see ChatMember} union.
 *
 * Wire discriminator: `status`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatMemberUnion
{
  /**
   * @return list<class-string<ChatMember>>
   */
  public static function members(): array
  {
    return [
      ChatMemberOwner::class,
      ChatMemberAdministrator::class,
      ChatMemberMember::class,
      ChatMemberRestricted::class,
      ChatMemberLeft::class,
      ChatMemberBanned::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): ChatMember
  {
    $discriminator = $payload['status'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'creator' => Serializer::load(ChatMemberOwner::class, $payload, $bot),
      'administrator' => Serializer::load(ChatMemberAdministrator::class, $payload, $bot),
      'member' => Serializer::load(ChatMemberMember::class, $payload, $bot),
      'restricted' => Serializer::load(ChatMemberRestricted::class, $payload, $bot),
      'left' => Serializer::load(ChatMemberLeft::class, $payload, $bot),
      'kicked' => Serializer::load(ChatMemberBanned::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown ChatMember status: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
