<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see StoryAreaType} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StoryAreaTypeUnion
{
  /**
   * @return list<class-string<StoryAreaType>>
   */
  public static function members(): array
  {
    return [
      StoryAreaTypeLocation::class,
      StoryAreaTypeSuggestedReaction::class,
      StoryAreaTypeLink::class,
      StoryAreaTypeWeather::class,
      StoryAreaTypeUniqueGift::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): StoryAreaType
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'location' => Serializer::load(StoryAreaTypeLocation::class, $payload, $bot),
      'suggested_reaction' => Serializer::load(StoryAreaTypeSuggestedReaction::class, $payload, $bot),
      'link' => Serializer::load(StoryAreaTypeLink::class, $payload, $bot),
      'weather' => Serializer::load(StoryAreaTypeWeather::class, $payload, $bot),
      'unique_gift' => Serializer::load(StoryAreaTypeUniqueGift::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown StoryAreaType type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
