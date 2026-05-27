<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

/**
 * Discriminator resolver for the {@see InlineQueryResult} union.
 *
 * Wire discriminator: `type`.
 *
 * NOTE: this union's children share wire discriminator values — two or more
 * subtypes declare the same `type` literal, so a
 * `match($payload['type'])` resolver would silently dispatch
 * every ambiguous payload to whichever subtype was registered first in
 * declaration order. The `resolve()` helper is intentionally omitted;
 * callers must dispatch via `instanceof` against the {@see members()}
 * listing or by inspecting payload-content keys that uniquely distinguish
 * the variants.
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
}
