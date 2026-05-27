<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents type of message entity
 *
 * Source: https://core.telegram.org/bots/api#messageentity
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum MessageEntityType: string
{
  case Mention = 'mention';
  case Hashtag = 'hashtag';
  case Cashtag = 'cashtag';
  case BotCommand = 'bot_command';
  case Url = 'url';
  case Email = 'email';
  case PhoneNumber = 'phone_number';
  case Bold = 'bold';
  case Italic = 'italic';
  case Underline = 'underline';
  case Strikethrough = 'strikethrough';
  case Spoiler = 'spoiler';
  case Blockquote = 'blockquote';
  case ExpandableBlockquote = 'expandable_blockquote';
  case Code = 'code';
  case Pre = 'pre';
  case TextLink = 'text_link';
  case TextMention = 'text_mention';
  case CustomEmoji = 'custom_emoji';
  case DateTime = 'date_time';
}
