<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see InputStoryContent} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputStoryContentUnion
{
  /**
   * @return list<class-string<InputStoryContent>>
   */
  public static function members(): array
  {
    return [
      InputStoryContentPhoto::class,
      InputStoryContentVideo::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): InputStoryContent
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'photo' => Serializer::load(InputStoryContentPhoto::class, $payload, $bot),
      'video' => Serializer::load(InputStoryContentVideo::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown InputStoryContent type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
