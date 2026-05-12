<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see InputMedia} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputMediaUnion
{
  /**
   * @return list<class-string<InputMedia>>
   */
  public static function members(): array
  {
    return [
      InputMediaAnimation::class,
      InputMediaAudio::class,
      InputMediaDocument::class,
      InputMediaLivePhoto::class,
      InputMediaPhoto::class,
      InputMediaVideo::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): InputMedia
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'animation' => Serializer::load(InputMediaAnimation::class, $payload, $bot),
      'audio' => Serializer::load(InputMediaAudio::class, $payload, $bot),
      'document' => Serializer::load(InputMediaDocument::class, $payload, $bot),
      'live_photo' => Serializer::load(InputMediaLivePhoto::class, $payload, $bot),
      'photo' => Serializer::load(InputMediaPhoto::class, $payload, $bot),
      'video' => Serializer::load(InputMediaVideo::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown InputMedia type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
