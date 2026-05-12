<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see BackgroundFill} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BackgroundFillUnion
{
  /**
   * @return list<class-string<BackgroundFill>>
   */
  public static function members(): array
  {
    return [
      BackgroundFillSolid::class,
      BackgroundFillGradient::class,
      BackgroundFillFreeformGradient::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): BackgroundFill
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'solid' => Serializer::load(BackgroundFillSolid::class, $payload, $bot),
      'gradient' => Serializer::load(BackgroundFillGradient::class, $payload, $bot),
      'freeform_gradient' => Serializer::load(BackgroundFillFreeformGradient::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown BackgroundFill type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
