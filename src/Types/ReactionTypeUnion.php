<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see ReactionType} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ReactionTypeUnion
{
  /**
   * @return list<class-string<ReactionType>>
   */
  public static function members(): array
  {
    return [
      ReactionTypeEmoji::class,
      ReactionTypeCustomEmoji::class,
      ReactionTypePaid::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): ReactionType
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'emoji' => Serializer::load(ReactionTypeEmoji::class, $payload, $bot),
      'custom_emoji' => Serializer::load(ReactionTypeCustomEmoji::class, $payload, $bot),
      'paid' => Serializer::load(ReactionTypePaid::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown ReactionType type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
