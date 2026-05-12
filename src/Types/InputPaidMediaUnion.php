<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see InputPaidMedia} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputPaidMediaUnion
{
  /**
   * @return list<class-string<InputPaidMedia>>
   */
  public static function members(): array
  {
    return [
      InputPaidMediaLivePhoto::class,
      InputPaidMediaPhoto::class,
      InputPaidMediaVideo::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): InputPaidMedia
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'live_photo' => Serializer::load(InputPaidMediaLivePhoto::class, $payload, $bot),
      'photo' => Serializer::load(InputPaidMediaPhoto::class, $payload, $bot),
      'video' => Serializer::load(InputPaidMediaVideo::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown InputPaidMedia type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
