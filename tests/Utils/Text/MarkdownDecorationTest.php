<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\Text;

use Gruven\PhpBotGram\Utils\Text\MarkdownDecoration;
use PHPUnit\Framework\TestCase;

/**
 * Thin subclass that promotes every protected decoration method to public so
 * unit tests can exercise them directly without going through `applyEntity`.
 */
final class MarkdownDecorationExposer extends MarkdownDecoration
{
  public function bold_(string $v): string
  {
    return $this->bold($v);
  }

  public function italic_(string $v): string
  {
    return $this->italic($v);
  }

  public function underline_(string $v): string
  {
    return $this->underline($v);
  }

  public function strikethrough_(string $v): string
  {
    return $this->strikethrough($v);
  }

  public function spoiler_(string $v): string
  {
    return $this->spoiler($v);
  }

  public function blockquote_(string $v): string
  {
    return $this->blockquote($v);
  }

  public function expandableBlockquote_(string $v): string
  {
    return $this->expandableBlockquote($v);
  }

  public function code_(string $v): string
  {
    return $this->code($v);
  }

  public function pre_(string $v): string
  {
    return $this->pre($v);
  }

  public function preLanguage_(string $v, string $lang): string
  {
    return $this->preLanguage($v, $lang);
  }

  public function link_(string $v, string $l): string
  {
    return $this->link($v, $l);
  }

  public function customEmoji_(string $v, string $id): string
  {
    return $this->customEmoji($v, $id);
  }

  public function dateTime_(string $v, int $t, ?string $f): string
  {
    return $this->dateTime($v, $t, $f);
  }
}

/**
 * Unit tests for `MarkdownDecoration` (Markdown V2).
 *
 * Covers `quote()` escaping, every decoration method, and the singleton.
 * Port of upstream `tests/test_utils/test_text_decorations.py` — Markdown cases.
 *
 * Upstream skips
 * --------------
 * - Markdown V1 (`test_markdown.py`): phpbotgram only ports Markdown V2 —
 *   phase scope deferral (b).
 * - `date_time` markdown URL uses `tg://time?unix=` in upstream; PHP uses
 *   `tg://datetime?unix_time=` — API divergence (a).
 * - `custom_emoji` markdown format `![text](tg://emoji?emoji_id=id)` in
 *   upstream; PHP uses `tg://emoji?id=` — API divergence (a).
 * - `test_date_time_with_datetime_object`: PHP `dateTime()` accepts
 *   `int $unixTime` only — API divergence (a).
 */
final class MarkdownDecorationTest extends TestCase
{
  private MarkdownDecoration $dec;

  private MarkdownDecorationExposer $exposer;

  protected function setUp(): void
  {
    $this->dec = MarkdownDecoration::instance();
    $this->exposer = new MarkdownDecorationExposer();
  }

  // -------------------------------------------------------------------------
  // Singleton
  // -------------------------------------------------------------------------

  public function testInstanceReturnsSameObject(): void
  {
    self::assertSame(MarkdownDecoration::instance(), MarkdownDecoration::instance());
  }

  // -------------------------------------------------------------------------
  // quote() — Markdown V2 escaping
  // -------------------------------------------------------------------------

  public function testQuoteEscapesUnderscore(): void
  {
    self::assertSame('\_', $this->dec->quote('_'));
  }

  public function testQuoteEscapesAsterisk(): void
  {
    self::assertSame('\*', $this->dec->quote('*'));
  }

  public function testQuoteEscapesSquareBrackets(): void
  {
    self::assertSame('\[\]', $this->dec->quote('[]'));
  }

  public function testQuoteEscapesParentheses(): void
  {
    self::assertSame('\(\)', $this->dec->quote('()'));
  }

  public function testQuoteEscapesTilde(): void
  {
    self::assertSame('\~', $this->dec->quote('~'));
  }

  public function testQuoteEscapesBacktick(): void
  {
    self::assertSame('\`', $this->dec->quote('`'));
  }

  public function testQuoteEscapesGreaterThan(): void
  {
    self::assertSame('\>', $this->dec->quote('>'));
  }

  public function testQuoteEscapesHash(): void
  {
    self::assertSame('\#', $this->dec->quote('#'));
  }

  public function testQuoteEscapesPlusAndMinus(): void
  {
    self::assertSame('\+\-', $this->dec->quote('+-'));
  }

  public function testQuoteEscapesEquals(): void
  {
    self::assertSame('\=', $this->dec->quote('='));
  }

  public function testQuoteEscapesPipe(): void
  {
    self::assertSame('\|', $this->dec->quote('|'));
  }

  public function testQuoteEscapesCurlyBraces(): void
  {
    self::assertSame('\{\}', $this->dec->quote('{}'));
  }

  public function testQuoteEscapesDot(): void
  {
    self::assertSame('\.', $this->dec->quote('.'));
  }

  public function testQuoteEscapesExclamation(): void
  {
    self::assertSame('\!', $this->dec->quote('!'));
  }

  public function testQuoteLeavesAlphanumericUnchanged(): void
  {
    self::assertSame('Hello World 123', $this->dec->quote('Hello World 123'));
  }

  public function testQuoteEscapesAllSpecialsInOnce(): void
  {
    // Verify every Markdown V2 special character is escaped in one pass.
    $input    = '_*[]()~`>#+-=|{}.!';
    $expected = '\_\*\[\]\(\)\~\`\>\#\+\-\=\|\{\}\.\!';
    self::assertSame($expected, $this->dec->quote($input));
  }

  // -------------------------------------------------------------------------
  // Decoration methods
  // -------------------------------------------------------------------------

  public function testBold(): void
  {
    self::assertSame('*hello*', $this->exposer->bold_('hello'));
  }

  public function testItalic(): void
  {
    self::assertSame("_\rhello_\r", $this->exposer->italic_('hello'));
  }

  public function testUnderline(): void
  {
    self::assertSame("__\rhello__\r", $this->exposer->underline_('hello'));
  }

  public function testStrikethrough(): void
  {
    self::assertSame('~hello~', $this->exposer->strikethrough_('hello'));
  }

  public function testSpoiler(): void
  {
    self::assertSame('||hello||', $this->exposer->spoiler_('hello'));
  }

  public function testCode(): void
  {
    self::assertSame('`hello`', $this->exposer->code_('hello'));
  }

  public function testPre(): void
  {
    self::assertSame("```\nhello\n```", $this->exposer->pre_('hello'));
  }

  public function testPreLanguage(): void
  {
    self::assertSame("```python\npass\n```", $this->exposer->preLanguage_('pass', 'python'));
  }

  public function testLink(): void
  {
    self::assertSame('[click](https://example.com)', $this->exposer->link_('click', 'https://example.com'));
  }

  public function testCustomEmoji(): void
  {
    self::assertSame('![😀](tg://emoji?id=999)', $this->exposer->customEmoji_('😀', '999'));
  }

  public function testDateTimeWithFormat(): void
  {
    self::assertSame('[now](tg://datetime?unix_time=0&format=HH:mm)', $this->exposer->dateTime_('now', 0, 'HH:mm'));
  }

  public function testDateTimeWithoutFormat(): void
  {
    self::assertSame('[now](tg://datetime?unix_time=0)', $this->exposer->dateTime_('now', 0, null));
  }

  public function testBlockquote(): void
  {
    self::assertSame(">line1\n>line2", $this->exposer->blockquote_("line1\nline2"));
  }

  public function testExpandableBlockquote(): void
  {
    self::assertSame(">line1\n>line2\n**", $this->exposer->expandableBlockquote_("line1\nline2"));
  }

  // -------------------------------------------------------------------------
  // Universal newline handling (\R — mirrors Python str.splitlines())
  // -------------------------------------------------------------------------

  public function testBlockquoteHandlesCrlfLineEndings(): void
  {
    // CRLF input must not embed \r in the output lines.
    self::assertSame(">line1\n>line2", $this->exposer->blockquote_("line1\r\nline2"));
  }

  public function testBlockquoteHandlesBareCarriageReturn(): void
  {
    // Bare \r must be treated as a line separator, same as \n.
    self::assertSame(">line1\n>line2", $this->exposer->blockquote_("line1\rline2"));
  }

  public function testExpandableBlockquoteHandlesCrlfLineEndings(): void
  {
    // CRLF input must not embed \r in the output lines.
    self::assertSame(">line1\n>line2\n**", $this->exposer->expandableBlockquote_("line1\r\nline2"));
  }

  public function testExpandableBlockquoteHandlesBareCarriageReturn(): void
  {
    // Bare \r must be treated as a line separator.
    self::assertSame(">line1\n>line2\n**", $this->exposer->expandableBlockquote_("line1\rline2"));
  }
}
