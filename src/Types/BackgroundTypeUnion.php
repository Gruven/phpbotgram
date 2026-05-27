<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see BackgroundType} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BackgroundTypeUnion
{
  /**
   * @return list<class-string<BackgroundType>>
   */
  public static function members(): array
  {
    return [
      BackgroundTypeFill::class,
      BackgroundTypeWallpaper::class,
      BackgroundTypePattern::class,
      BackgroundTypeChatTheme::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): BackgroundType
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'fill' => Serializer::load(BackgroundTypeFill::class, $payload, $bot),
      'wallpaper' => Serializer::load(BackgroundTypeWallpaper::class, $payload, $bot),
      'pattern' => Serializer::load(BackgroundTypePattern::class, $payload, $bot),
      'chat_theme' => Serializer::load(BackgroundTypeChatTheme::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown BackgroundType type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
