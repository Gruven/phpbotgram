<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a rich formatted text. Currently, it can be either a String for plain text, an Array of RichText, or any of the following types:
 *  - RichTextBold
 *  - RichTextItalic
 *  - RichTextUnderline
 *  - RichTextStrikethrough
 *  - RichTextSpoiler
 *  - RichTextDateTime
 *  - RichTextTextMention
 *  - RichTextSubscript
 *  - RichTextSuperscript
 *  - RichTextMarked
 *  - RichTextCode
 *  - RichTextCustomEmoji
 *  - RichTextMathematicalExpression
 *  - RichTextUrl
 *  - RichTextEmailAddress
 *  - RichTextPhoneNumber
 *  - RichTextBankCardNumber
 *  - RichTextMention
 *  - RichTextHashtag
 *  - RichTextCashtag
 *  - RichTextBotCommand
 *  - RichTextAnchor
 *  - RichTextAnchorLink
 *  - RichTextReference
 *  - RichTextReferenceLink
 *
 * Source: https://core.telegram.org/bots/api#richtext
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class RichText extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
