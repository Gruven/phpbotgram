<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see OwnedGift} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class OwnedGiftUnion
{
  /**
   * @return list<class-string<OwnedGift>>
   */
  public static function members(): array
  {
    return [
      OwnedGiftRegular::class,
      OwnedGiftUnique::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): OwnedGift
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'regular' => Serializer::load(OwnedGiftRegular::class, $payload, $bot),
      'unique' => Serializer::load(OwnedGiftUnique::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown OwnedGift type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
