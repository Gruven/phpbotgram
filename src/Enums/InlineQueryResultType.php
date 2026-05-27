<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * Type of inline query result
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresult
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum InlineQueryResultType: string
{
  case Audio = 'audio';
  case Document = 'document';
  case Gif = 'gif';
  case Mpeg4Gif = 'mpeg4_gif';
  case Photo = 'photo';
  case Sticker = 'sticker';
  case Video = 'video';
  case Voice = 'voice';
  case Article = 'article';
  case Contact = 'contact';
  case Game = 'game';
  case Location = 'location';
  case Venue = 'venue';
}
