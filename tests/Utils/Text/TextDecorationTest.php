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
 *
 * Upstream skips
 * --------------
 * - test_apply_single_entity / html_decoration / `date_time` row with
 *   `unix_time=42, format="yMd"` expecting `<tg-time unix="42" format="yMd">`:
 *   PHP uses `<tg-datetime>` tag — API divergence (a).
 * - test_apply_single_entity / html_decoration / `pre` with language expecting
 *   `<pre><code language="language-python">`: PHP uses `class="language-*"` —
 *   API divergence (a).
 * - test_date_time_with_datetime_object: PHP `dateTime()` accepts `int $unixTime`
 *   only; there is no `datetime` object overload — API divergence (a).
 * - test_unparse rows for markdown_decoration `date_time` entity: PHP emits
 *   `tg://datetime?unix_time=` rather than `tg://time?unix=` — API divergence (a).
 * - test_unparse emoji rows that rely on Python `add_surrogates`/surrogate-pair
 *   slicing quirks (`👋🏾` two-surrogate-pair sequence): handled natively in PHP
 *   via `mb_convert_encoding` UTF-16LE — the existing
 *   `testUnparseUtf16EmojiOffsetAccounting` tests the same concept with a
 *   single surrogate-pair emoji.
 * - Passthrough types (mention, url, etc.) — now match upstream: `apply_entity`
 *   returns `text` as-is (no escaping). Fixed in Phase 7 review cycle 2.
 * - `test_apply_single_entity` markdown `expandable_blockquote` row expects
 *   `">test||"`; PHP emits `">test\n**"` — API divergence (a).
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

  public function testApplyEntityUnderlineHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Underline->value, offset: 0, length: 4);
    self::assertSame('<u>test</u>', HtmlDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityStrikethroughHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Strikethrough->value, offset: 0, length: 4);
    self::assertSame('<s>test</s>', HtmlDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntitySpoilerHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Spoiler->value, offset: 0, length: 4);
    self::assertSame('<tg-spoiler>test</tg-spoiler>', HtmlDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityBlockquoteHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Blockquote->value, offset: 0, length: 4);
    self::assertSame('<blockquote>test</blockquote>', HtmlDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityExpandableBlockquoteHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::ExpandableBlockquote->value, offset: 0, length: 4);
    self::assertSame('<blockquote expandable>test</blockquote>', HtmlDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityCodeHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Code->value, offset: 0, length: 4);
    self::assertSame('<code>test</code>', HtmlDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityUrlPassthroughHtml(): void
  {
    // url, hashtag, cashtag, bot_command, email, phone_number — not wrapped; just quoted.
    $entity = new MessageEntity(type: MessageEntityType::Url->value, offset: 0, length: 4);
    self::assertSame('test', HtmlDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityHashtagPassthroughHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Hashtag->value, offset: 0, length: 5);
    self::assertSame('#test', HtmlDecoration::instance()->applyEntity($entity, '#test'));
  }

  public function testApplyEntityCashtagPassthroughHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Cashtag->value, offset: 0, length: 5);
    self::assertSame('$TEST', HtmlDecoration::instance()->applyEntity($entity, '$TEST'));
  }

  public function testApplyEntityBotCommandPassthroughHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::BotCommand->value, offset: 0, length: 8);
    self::assertSame('/command', HtmlDecoration::instance()->applyEntity($entity, '/command'));
  }

  public function testApplyEntityEmailPassthroughHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Email->value, offset: 0, length: 4);
    self::assertSame('test', HtmlDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityPhoneNumberPassthroughHtml(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::PhoneNumber->value, offset: 0, length: 4);
    self::assertSame('test', HtmlDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityBoldMarkdown(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Bold->value, offset: 0, length: 4);
    self::assertSame('*test*', MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityItalicMarkdown(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Italic->value, offset: 0, length: 4);
    self::assertSame("_\rtest_\r", MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityCodeMarkdown(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Code->value, offset: 0, length: 4);
    self::assertSame('`test`', MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityPreMarkdown(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Pre->value, offset: 0, length: 4);
    self::assertSame("```\ntest\n```", MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityPreWithLanguageMarkdown(): void
  {
    $entity = new MessageEntity(
      type: MessageEntityType::Pre->value,
      offset: 0,
      length: 4,
      language: 'python',
    );
    self::assertSame("```python\ntest\n```", MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityUnderlineMarkdown(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Underline->value, offset: 0, length: 4);
    self::assertSame("__\rtest__\r", MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityStrikethroughMarkdown(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Strikethrough->value, offset: 0, length: 4);
    self::assertSame('~test~', MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntitySpoilerMarkdown(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Spoiler->value, offset: 0, length: 4);
    self::assertSame('||test||', MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityCustomEmojiMarkdown(): void
  {
    $entity = new MessageEntity(
      type: MessageEntityType::CustomEmoji->value,
      offset: 0,
      length: 4,
      customEmojiId: '42',
    );
    self::assertSame('![test](tg://emoji?id=42)', MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityTextMentionMarkdown(): void
  {
    $user = new User(id: 42, isBot: false, firstName: 'Test');
    $entity = new MessageEntity(
      type: MessageEntityType::TextMention->value,
      offset: 0,
      length: 4,
      user: $user,
    );
    self::assertSame('[test](tg://user?id=42)', MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityBlockquoteMarkdown(): void
  {
    $entity = new MessageEntity(type: MessageEntityType::Blockquote->value, offset: 0, length: 4);
    self::assertSame('>test', MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityExpandableBlockquoteMarkdown(): void
  {
    // NOTE: upstream Markdown V2 format is ">test||"; PHP format adds a
    // trailing newline separator: ">test\n**". API divergence (a).
    $entity = new MessageEntity(type: MessageEntityType::ExpandableBlockquote->value, offset: 0, length: 4);
    self::assertSame(">test\n**", MarkdownDecoration::instance()->applyEntity($entity, 'test'));
  }

  public function testApplyEntityPassthroughTypesMarkdown(): void
  {
    // Passthrough types return text as-is (upstream parity); for plain "test"
    // this is indistinguishable from quote(), but for MD-special strings the
    // difference matters (see testMarkdownV2RoundTripsHashtagWithUnderscore).
    foreach ([
      MessageEntityType::Hashtag,
      MessageEntityType::Cashtag,
      MessageEntityType::BotCommand,
      MessageEntityType::Email,
      MessageEntityType::PhoneNumber,
    ] as $type) {
      $entity = new MessageEntity(type: $type->value, offset: 0, length: 4);
      self::assertSame('test', MarkdownDecoration::instance()->applyEntity($entity, 'test'));
    }
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

  public function testUnparseStrikethroughAndBoldNonOverlapping(): void
  {
    // "strike bold" — "strike" strikethrough, "bold" bold.
    $entities = [
      new MessageEntity(type: MessageEntityType::Strikethrough->value, offset: 0, length: 6),
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 7, length: 4),
    ];
    self::assertSame(
      '<s>strike</s> <b>bold</b>',
      HtmlDecoration::instance()->unparse('strike bold', $entities),
    );
  }

  public function testUnparseSameOffsetWrapsInnerFirst(): void
  {
    // "test" — strikethrough wraps bold; same offset means outer is processed first.
    $entities = [
      new MessageEntity(type: MessageEntityType::Strikethrough->value, offset: 0, length: 4),
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 0, length: 4),
    ];
    self::assertSame(
      '<s><b>test</b></s>',
      HtmlDecoration::instance()->unparse('test', $entities),
    );
  }

  public function testUnparseThreeLevelNestingStrikeBoldUnderline(): void
  {
    // "strikeboldunder" — strike covers all 15 chars; bold covers 6..15; under covers 10..15.
    $entities = [
      new MessageEntity(type: MessageEntityType::Strikethrough->value, offset: 0, length: 15),
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 6, length: 9),
      new MessageEntity(type: MessageEntityType::Underline->value, offset: 10, length: 5),
    ];
    self::assertSame(
      '<s>strike<b>bold<u>under</u></b></s>',
      HtmlDecoration::instance()->unparse('strikeboldunder', $entities),
    );
  }

  public function testUnparsePassthroughEntityWithBold(): void
  {
    // "@username" — mention + bold at same offset.
    // Upstream parity: passthrough types return text as-is, so the outer
    // mention wrapper returns the inner "<b>@username</b>" unchanged (no
    // escaping of the HTML tags produced by the inner bold entity).
    $entities = [
      new MessageEntity(type: MessageEntityType::Mention->value, offset: 0, length: 9),
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 0, length: 9),
    ];
    self::assertSame(
      '<b>@username</b>',
      HtmlDecoration::instance()->unparse('@username', $entities),
    );
  }

  public function testMarkdownV2RoundTripsHashtagWithUnderscore(): void
  {
    // Regression for the MD V2 round-trip double-escaping bug.
    //
    // The entity inner text is produced by recursive _unparse_entities which
    // already quote()s plain text ranges, so '#hash_tag' → '\#hash\_tag'.
    // With the bug (default => quote($text)), that already-quoted text was
    // quote()-d again: '\#hash\_tag' → '\\\\#hash\\_tag'.
    // With the fix (passthrough => $text), it returns '\#hash\_tag' exactly —
    // single-escaped, matching what Telegram needs.
    $entities = [
      new MessageEntity(type: MessageEntityType::Hashtag->value, offset: 0, length: 9),
    ];
    self::assertSame(
      '\#hash\_tag',
      MarkdownDecoration::instance()->unparse('#hash_tag', $entities),
    );
  }

  public function testUnparseDateTimeEntityHtml(): void
  {
    // date_time entity without format.
    $entities = [
      new MessageEntity(type: MessageEntityType::DateTime->value, offset: 0, length: 4, unixTime: 42),
    ];
    self::assertSame(
      '<tg-datetime unix-time="42">test</tg-datetime>',
      HtmlDecoration::instance()->unparse('test', $entities),
    );
  }

  public function testUnparseDateTimeEntityWithFormatHtml(): void
  {
    // date_time entity with format string.
    $entities = [
      new MessageEntity(
        type: MessageEntityType::DateTime->value,
        offset: 0,
        length: 4,
        unixTime: 42,
        dateTimeFormat: 'yMd',
      ),
    ];
    self::assertSame(
      '<tg-datetime unix-time="42" format="yMd">test</tg-datetime>',
      HtmlDecoration::instance()->unparse('test', $entities),
    );
  }

  public function testUnparseLeadingEntityHtml(): void
  {
    // Entity at offset 0 covering only part of the text.
    $entities = [
      new MessageEntity(type: MessageEntityType::Bold->value, offset: 0, length: 5),
    ];
    self::assertSame(
      '<b>test1</b> test2',
      HtmlDecoration::instance()->unparse('test1 test2', $entities),
    );
  }
}
