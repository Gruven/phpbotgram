<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see InputPollOptionMedia} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputPollOptionMediaUnion
{
  /**
   * @return list<class-string<InputPollOptionMediaInterface>>
   */
  public static function members(): array
  {
    return [
      InputMediaAnimation::class,
      InputMediaLink::class,
      InputMediaLivePhoto::class,
      InputMediaLocation::class,
      InputMediaPhoto::class,
      InputMediaSticker::class,
      InputMediaVenue::class,
      InputMediaVideo::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): InputPollOptionMediaInterface
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'animation' => Serializer::load(InputMediaAnimation::class, $payload, $bot),
      'link' => Serializer::load(InputMediaLink::class, $payload, $bot),
      'live_photo' => Serializer::load(InputMediaLivePhoto::class, $payload, $bot),
      'location' => Serializer::load(InputMediaLocation::class, $payload, $bot),
      'photo' => Serializer::load(InputMediaPhoto::class, $payload, $bot),
      'sticker' => Serializer::load(InputMediaSticker::class, $payload, $bot),
      'venue' => Serializer::load(InputMediaVenue::class, $payload, $bot),
      'video' => Serializer::load(InputMediaVideo::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown InputPollOptionMedia type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
