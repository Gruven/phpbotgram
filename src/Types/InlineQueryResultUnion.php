<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see InlineQueryResult} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultUnion
{
  /**
   * @return list<class-string<InlineQueryResult>>
   */
  public static function members(): array
  {
    return [
      InlineQueryResultCachedAudio::class,
      InlineQueryResultCachedDocument::class,
      InlineQueryResultCachedGif::class,
      InlineQueryResultCachedMpeg4Gif::class,
      InlineQueryResultCachedPhoto::class,
      InlineQueryResultCachedSticker::class,
      InlineQueryResultCachedVideo::class,
      InlineQueryResultCachedVoice::class,
      InlineQueryResultArticle::class,
      InlineQueryResultAudio::class,
      InlineQueryResultContact::class,
      InlineQueryResultGame::class,
      InlineQueryResultDocument::class,
      InlineQueryResultGif::class,
      InlineQueryResultLocation::class,
      InlineQueryResultMpeg4Gif::class,
      InlineQueryResultPhoto::class,
      InlineQueryResultVenue::class,
      InlineQueryResultVideo::class,
      InlineQueryResultVoice::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): InlineQueryResult
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'audio' => Serializer::load(InlineQueryResultCachedAudio::class, $payload, $bot),
      'document' => Serializer::load(InlineQueryResultCachedDocument::class, $payload, $bot),
      'gif' => Serializer::load(InlineQueryResultCachedGif::class, $payload, $bot),
      'mpeg4_gif' => Serializer::load(InlineQueryResultCachedMpeg4Gif::class, $payload, $bot),
      'photo' => Serializer::load(InlineQueryResultCachedPhoto::class, $payload, $bot),
      'sticker' => Serializer::load(InlineQueryResultCachedSticker::class, $payload, $bot),
      'video' => Serializer::load(InlineQueryResultCachedVideo::class, $payload, $bot),
      'voice' => Serializer::load(InlineQueryResultCachedVoice::class, $payload, $bot),
      'article' => Serializer::load(InlineQueryResultArticle::class, $payload, $bot),
      'contact' => Serializer::load(InlineQueryResultContact::class, $payload, $bot),
      'game' => Serializer::load(InlineQueryResultGame::class, $payload, $bot),
      'location' => Serializer::load(InlineQueryResultLocation::class, $payload, $bot),
      'venue' => Serializer::load(InlineQueryResultVenue::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown InlineQueryResult type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
