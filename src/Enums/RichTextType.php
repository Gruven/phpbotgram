<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents the rich text type.
 *
 * Source: https://core.telegram.org/bots/api#richtext
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum RichTextType: string
{
  case Bold = 'bold';
  case Italic = 'italic';
  case Underline = 'underline';
  case Strikethrough = 'strikethrough';
  case Spoiler = 'spoiler';
  case DateTime = 'date_time';
  case TextMention = 'text_mention';
  case Subscript = 'subscript';
  case Superscript = 'superscript';
  case Marked = 'marked';
  case Code = 'code';
  case CustomEmoji = 'custom_emoji';
  case MathematicalExpression = 'mathematical_expression';
  case Url = 'url';
  case EmailAddress = 'email_address';
  case PhoneNumber = 'phone_number';
  case BankCardNumber = 'bank_card_number';
  case Mention = 'mention';
  case Hashtag = 'hashtag';
  case Cashtag = 'cashtag';
  case BotCommand = 'bot_command';
  case Anchor = 'anchor';
  case AnchorLink = 'anchor_link';
  case Reference = 'reference';
  case ReferenceLink = 'reference_link';
}
