<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see BotCommandScope} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotCommandScopeUnion
{
  /**
   * @return list<class-string<BotCommandScope>>
   */
  public static function members(): array
  {
    return [
      BotCommandScopeDefault::class,
      BotCommandScopeAllPrivateChats::class,
      BotCommandScopeAllGroupChats::class,
      BotCommandScopeAllChatAdministrators::class,
      BotCommandScopeChat::class,
      BotCommandScopeChatAdministrators::class,
      BotCommandScopeChatMember::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): BotCommandScope
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'default' => Serializer::load(BotCommandScopeDefault::class, $payload, $bot),
      'all_private_chats' => Serializer::load(BotCommandScopeAllPrivateChats::class, $payload, $bot),
      'all_group_chats' => Serializer::load(BotCommandScopeAllGroupChats::class, $payload, $bot),
      'all_chat_administrators' => Serializer::load(BotCommandScopeAllChatAdministrators::class, $payload, $bot),
      'chat' => Serializer::load(BotCommandScopeChat::class, $payload, $bot),
      'chat_administrators' => Serializer::load(BotCommandScopeChatAdministrators::class, $payload, $bot),
      'chat_member' => Serializer::load(BotCommandScopeChatMember::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown BotCommandScope type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
