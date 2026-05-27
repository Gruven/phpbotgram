<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see PassportElementError} union.
 *
 * Wire discriminator: `source`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PassportElementErrorUnion
{
  /**
   * @return list<class-string<PassportElementError>>
   */
  public static function members(): array
  {
    return [
      PassportElementErrorDataField::class,
      PassportElementErrorFrontSide::class,
      PassportElementErrorReverseSide::class,
      PassportElementErrorSelfie::class,
      PassportElementErrorFile::class,
      PassportElementErrorFiles::class,
      PassportElementErrorTranslationFile::class,
      PassportElementErrorTranslationFiles::class,
      PassportElementErrorUnspecified::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): PassportElementError
  {
    $discriminator = $payload['source'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'data' => Serializer::load(PassportElementErrorDataField::class, $payload, $bot),
      'front_side' => Serializer::load(PassportElementErrorFrontSide::class, $payload, $bot),
      'reverse_side' => Serializer::load(PassportElementErrorReverseSide::class, $payload, $bot),
      'selfie' => Serializer::load(PassportElementErrorSelfie::class, $payload, $bot),
      'file' => Serializer::load(PassportElementErrorFile::class, $payload, $bot),
      'files' => Serializer::load(PassportElementErrorFiles::class, $payload, $bot),
      'translation_file' => Serializer::load(PassportElementErrorTranslationFile::class, $payload, $bot),
      'translation_files' => Serializer::load(PassportElementErrorTranslationFiles::class, $payload, $bot),
      'unspecified' => Serializer::load(PassportElementErrorUnspecified::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown PassportElementError source: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
