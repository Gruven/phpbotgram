<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see MessageOrigin} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MessageOriginUnion
{
  /**
   * @return list<class-string<MessageOrigin>>
   */
  public static function members(): array
  {
    return [
      MessageOriginUser::class,
      MessageOriginHiddenUser::class,
      MessageOriginChat::class,
      MessageOriginChannel::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): MessageOrigin
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'user' => Serializer::load(MessageOriginUser::class, $payload, $bot),
      'hidden_user' => Serializer::load(MessageOriginHiddenUser::class, $payload, $bot),
      'chat' => Serializer::load(MessageOriginChat::class, $payload, $bot),
      'channel' => Serializer::load(MessageOriginChannel::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown MessageOrigin type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
