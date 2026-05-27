<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see PaidMedia} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PaidMediaUnion
{
  /**
   * @return list<class-string<PaidMedia>>
   */
  public static function members(): array
  {
    return [
      PaidMediaLivePhoto::class,
      PaidMediaPhoto::class,
      PaidMediaPreview::class,
      PaidMediaVideo::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): PaidMedia
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'live_photo' => Serializer::load(PaidMediaLivePhoto::class, $payload, $bot),
      'photo' => Serializer::load(PaidMediaPhoto::class, $payload, $bot),
      'preview' => Serializer::load(PaidMediaPreview::class, $payload, $bot),
      'video' => Serializer::load(PaidMediaVideo::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown PaidMedia type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
