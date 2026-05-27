<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see InputProfilePhoto} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputProfilePhotoUnion
{
  /**
   * @return list<class-string<InputProfilePhoto>>
   */
  public static function members(): array
  {
    return [
      InputProfilePhotoStatic::class,
      InputProfilePhotoAnimated::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): InputProfilePhoto
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'static' => Serializer::load(InputProfilePhotoStatic::class, $payload, $bot),
      'animated' => Serializer::load(InputProfilePhotoAnimated::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown InputProfilePhoto type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
