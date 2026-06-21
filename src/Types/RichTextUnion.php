<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see RichText} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextUnion
{
  /**
   * @return list<class-string<RichText>>
   */
  public static function members(): array
  {
    return [
      RichTextBold::class,
      RichTextItalic::class,
      RichTextUnderline::class,
      RichTextStrikethrough::class,
      RichTextSpoiler::class,
      RichTextDateTime::class,
      RichTextTextMention::class,
      RichTextSubscript::class,
      RichTextSuperscript::class,
      RichTextMarked::class,
      RichTextCode::class,
      RichTextCustomEmoji::class,
      RichTextMathematicalExpression::class,
      RichTextUrl::class,
      RichTextEmailAddress::class,
      RichTextPhoneNumber::class,
      RichTextBankCardNumber::class,
      RichTextMention::class,
      RichTextHashtag::class,
      RichTextCashtag::class,
      RichTextBotCommand::class,
      RichTextAnchor::class,
      RichTextAnchorLink::class,
      RichTextReference::class,
      RichTextReferenceLink::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): RichText
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'bold' => Serializer::load(RichTextBold::class, $payload, $bot),
      'italic' => Serializer::load(RichTextItalic::class, $payload, $bot),
      'underline' => Serializer::load(RichTextUnderline::class, $payload, $bot),
      'strikethrough' => Serializer::load(RichTextStrikethrough::class, $payload, $bot),
      'spoiler' => Serializer::load(RichTextSpoiler::class, $payload, $bot),
      'date_time' => Serializer::load(RichTextDateTime::class, $payload, $bot),
      'text_mention' => Serializer::load(RichTextTextMention::class, $payload, $bot),
      'subscript' => Serializer::load(RichTextSubscript::class, $payload, $bot),
      'superscript' => Serializer::load(RichTextSuperscript::class, $payload, $bot),
      'marked' => Serializer::load(RichTextMarked::class, $payload, $bot),
      'code' => Serializer::load(RichTextCode::class, $payload, $bot),
      'custom_emoji' => Serializer::load(RichTextCustomEmoji::class, $payload, $bot),
      'mathematical_expression' => Serializer::load(RichTextMathematicalExpression::class, $payload, $bot),
      'url' => Serializer::load(RichTextUrl::class, $payload, $bot),
      'email_address' => Serializer::load(RichTextEmailAddress::class, $payload, $bot),
      'phone_number' => Serializer::load(RichTextPhoneNumber::class, $payload, $bot),
      'bank_card_number' => Serializer::load(RichTextBankCardNumber::class, $payload, $bot),
      'mention' => Serializer::load(RichTextMention::class, $payload, $bot),
      'hashtag' => Serializer::load(RichTextHashtag::class, $payload, $bot),
      'cashtag' => Serializer::load(RichTextCashtag::class, $payload, $bot),
      'bot_command' => Serializer::load(RichTextBotCommand::class, $payload, $bot),
      'anchor' => Serializer::load(RichTextAnchor::class, $payload, $bot),
      'anchor_link' => Serializer::load(RichTextAnchorLink::class, $payload, $bot),
      'reference' => Serializer::load(RichTextReference::class, $payload, $bot),
      'reference_link' => Serializer::load(RichTextReferenceLink::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown RichText type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
