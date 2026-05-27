<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see ChatBoostSource} union.
 *
 * Wire discriminator: `source`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatBoostSourceUnion
{
  /**
   * @return list<class-string<ChatBoostSource>>
   */
  public static function members(): array
  {
    return [
      ChatBoostSourcePremium::class,
      ChatBoostSourceGiftCode::class,
      ChatBoostSourceGiveaway::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): ChatBoostSource
  {
    $discriminator = $payload['source'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'premium' => Serializer::load(ChatBoostSourcePremium::class, $payload, $bot),
      'gift_code' => Serializer::load(ChatBoostSourceGiftCode::class, $payload, $bot),
      'giveaway' => Serializer::load(ChatBoostSourceGiveaway::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown ChatBoostSource source: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
