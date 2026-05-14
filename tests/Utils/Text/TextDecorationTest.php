<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\Text;

use Gruven\PhpBotGram\Enums\MessageEntityType;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\Text\HtmlDecoration;
use Gruven\PhpBotGram\Utils\Text\MarkdownDecoration;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `TextDecoration::applyEntity()` and `TextDecoration::unparse()`
 * shared behaviour exercised via the concrete HtmlDecoration and
 * MarkdownDecoration subclasses.
 *
 * Port of upstream `tests/test_utils/test_text_decorations.py`.
 */
final class TextDecorationTest extends TestCase
{
  // -------------------------------------------------------------------------
  // applyEntity dispatch
  // -------------------------------------------------------------------------

  public function testApplyEntityBoldHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Bold->value, offset: 0, length: 4);
    self::assertSame('<b>text</b>', HtmlDecoration::instance()->applyEntity($entity, 'text'));
  }

  public function testApplyEntityItalicHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Italic->value, offset: 0, length: 4);
    self::assertSame('<i>text</i>', HtmlDecoration::instance()->applyEntity($entity, 'text'));
  }

  public function testApplyEntityPreWithLanguageHtml(): void
  {
    $entity = new MessageEntity(
      type: MessageEntityType::Pre->value,
      offset: 0,
      length: 4,
      language: 'php',
    );
    self::assertSame(
      '<pre><code class="language-php">text</code></pre>',
      HtmlDecoration::instance()->applyEntity($entity, 'text'),
    );
  }

  public function testApplyEntityPreWithoutLanguageHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Pre->value, offset: 0, length: 4);
    self::assertSame('<pre>text</pre>', HtmlDecoration::instance()->applyEntity($entity, 'text'));
  }

  public function testApplyEntityTextLinkHtml(): void
  {
    $entity = new MessageEntity(
      type: MessageEntityType::TextLink->value,
      offset: 0,
      length: 4,
      url: 'https://example.com',
    );
    self::assertSame(
      '<a href="https://example.com">text</a>',
      HtmlDecoration::instance()->applyEntity($entity, 'text'),
    );
  }

  public function testApplyEntityTextMentionHtml(): void
  {
    $user = new User(id: 123456, isBot: false, firstName: 'Alice');
    $entity = new MessageEntity(
      type: MessageEntityType::TextMention->value,
      offset: 0,
      length: 5,
      user: $user,
    );
    self::assertSame(
      '<a href="tg://user?id=123456">Alice</a>',
      HtmlDecoration::instance()->applyEntity($entity, 'Alice'),
    );
  }

  public function testApplyEntityCustomEmojiHtml(): void
  {
    $entity = new MessageEntity(
      type: MessageEntityType::CustomEmoji->value,
      offset: 0,
      length: 2,
      customEmojiId: '5368324170671202286',
    );
    self::assertSame(
      '<tg-emoji emoji-id="5368324170671202286">😀</tg-emoji>',
      HtmlDecoration::instance()->applyEntity($entity, '😀'),
    );
  }

  public function testApplyEntityDateTimeHtml(): void
  {
    $entity = new MessageEntity(
      type: MessageEntityType::DateTime->value,
      offset: 0,
      length: 5,
      unixTime: 1700000000,
      dateTimeFormat: 'HH:mm',
    );
    self::assertSame(
      '<tg-datetime unix-time="1700000000" format="HH:mm">today</tg-datetime>',
      HtmlDecoration::instance()->applyEntity($entity, 'today'),
    );
  }

  public function testApplyEntityDateTimeWithoutFormatHtml(): void
  {
    $entity = new MessageEntity(
      type: MessageEntityType::DateTime->value,
      offset: 0,
      length: 5,
      unixTime: 1700000000,
    );
    self::assertSame(
      '<tg-datetime unix-time="1700000000">today</tg-datetime>',
      HtmlDecoration::instance()->applyEntity($entity, 'today'),
    );
  }

  public function testApplyEntityNonDecoratingTypeFallsBackToQuoteHtml(): void
  {
    // Mention, hashtag, bot_command, url — not wrapped in tags; just quoted.
    $entity = new MessageEntity(type: MessageEntityType::Mention->value, offset: 0, length: 5);
    // '@user' has no special HTML chars so quote leaves it unchanged.
    self::assertSame('@user', HtmlDecoration::instance()->applyEntity($entity, '@user'));
  }

  // -------------------------------------------------------------------------
  // unparse — base behaviour shared by both subclasses
  // -------------------------------------------------------------------------

  public function testUnparseNoEntitiesReturnsQuotedText(): void
  {
    // With no entities the whole text is passed through quote().
    // '<Hello>' must be HTML-escaped.
    self::assertSame('&lt;Hello&gt;', HtmlDecoration::instance()->unparse('<Hello>'));
  }

  public function testUnparseEmptyEntitiesListReturnsQuotedText(): void
  {
    self::assertSame('plain', HtmlDecoration::instance()->unparse('plain', []));
  }

  public function testUnparseSingleEntityCoveringWholeText(): void
  {
    $entities = [
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 0, length: 4),
    ];
    self::assertSame('<b>text</b>', HtmlDecoration::instance()->unparse('text', $entities));
  }

  public function testUnparseMultipleNonOverlappingEntities(): void
  {
    // "Hello World" — "Hello" bold, space plain, "World" italic.
    $entities = [
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 0, length: 5),
      new MessageEntity(type: MessageEntityType::Italic->value, offset: 6, length: 5),
    ];
    self::assertSame(
      '<b>Hello</b> <i>World</i>',
      HtmlDecoration::instance()->unparse('Hello World', $entities),
    );
  }

  public function testUnparseNestedEntitiesBoldInsideLink(): void
  {
    // "click here" — whole text is a link, "click" is also bold.
    // Offset 0..5 = "click" bold, offset 0..10 = "click here" link.
    $entities = [
      new MessageEntity(type: MessageEntityType::TextLink->value, offset: 0, length: 10, url: 'https://t.me'),
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 0, length: 5),
    ];
    // The link wraps the entire inner text; bold wraps "click".
    self::assertSame(
      '<a href="https://t.me"><b>click</b> here</a>',
      HtmlDecoration::instance()->unparse('click here', $entities),
    );
  }

  public function testUnparseUtf16EmojiOffsetAccounting(): void
  {
    // "😀 hi" — emoji is 4 UTF-8 bytes but 2 UTF-16 code units (one
    // surrogate pair). The entity "hi" starts at UTF-16 offset 3
    // (2 units for emoji + 1 for space).
    $entities = [
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 3, length: 2),
    ];
    self::assertSame('😀 <b>hi</b>', HtmlDecoration::instance()->unparse('😀 hi', $entities));
  }

  public function testUnparseCyrillicOffsetAccounting(): void
  {
    // Cyrillic chars are in BMP → each is 1 UTF-16 code unit.
    // "Привет" = 6 code units, bold the whole word (offset 0, length 6).
    $entities = [
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 0, length: 6),
    ];
    self::assertSame('<b>Привет</b>', HtmlDecoration::instance()->unparse('Привет', $entities));
  }

  public function testUnparseEntitiesAreSortedByOffset(): void
  {
    // Even if entities are provided out of order they must be sorted.
    $entities = [
      new MessageEntity(type: MessageEntityType::Italic->value, offset: 6, length: 5),
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 0, length: 5),
    ];
    self::assertSame(
      '<b>Hello</b> <i>World</i>',
      HtmlDecoration::instance()->unparse('Hello World', $entities),
    );
  }

  public function testUnparseMarkdownNestedEntities(): void
  {
    // "click here" — link wraps whole text, "here" (offset 6) is bold.
    $entities = [
      new MessageEntity(type: MessageEntityType::TextLink->value, offset: 0, length: 10, url: 'https://t.me'),
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 6, length: 4),
    ];
    self::assertSame(
      '[click *here*](https://t.me)',
      MarkdownDecoration::instance()->unparse('click here', $entities),
    );
  }
}
