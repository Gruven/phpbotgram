<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\Text;

use Gruven\PhpBotGram\Utils\Text\HtmlDecoration;
use PHPUnit\Framework\TestCase;

/**
 * Thin subclass that promotes every protected decoration method to public so
 * unit tests can exercise them directly without going through `applyEntity`.
 */
final class HtmlDecorationExposer extends HtmlDecoration
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
 * Unit tests for `HtmlDecoration`.
 *
 * Covers every decoration method and `quote()`, plus the singleton accessor.
 * Port of upstream `tests/test_utils/test_text_decorations.py` — HTML cases.
 *
 * Upstream skips
 * --------------
 * - `pre` with language expects `<pre><code language="language-python">` in
 *   upstream; PHP emits `class="language-python"` — API divergence (a).
 * - `date_time` entity tag name is `<tg-time>` in upstream; PHP uses
 *   `<tg-datetime>` — API divergence (a).
 * - `test_date_time_with_datetime_object`: PHP `dateTime()` accepts
 *   `int $unixTime` only — API divergence (a).
 */
final class HtmlDecorationTest extends TestCase
{
  private HtmlDecoration $dec;

  private HtmlDecorationExposer $exposer;

  protected function setUp(): void
  {
    $this->dec = HtmlDecoration::instance();
    $this->exposer = new HtmlDecorationExposer();
  }

  // -------------------------------------------------------------------------
  // Singleton
  // -------------------------------------------------------------------------

  public function testInstanceReturnsSameObject(): void
  {
    self::assertSame(HtmlDecoration::instance(), HtmlDecoration::instance());
  }

  // -------------------------------------------------------------------------
  // quote()
  // -------------------------------------------------------------------------

  public function testQuoteEscapesAmpersand(): void
  {
    self::assertSame('a&amp;b', $this->dec->quote('a&b'));
  }

  public function testQuoteEscapesLessThan(): void
  {
    self::assertSame('&lt;', $this->dec->quote('<'));
  }

  public function testQuoteEscapesGreaterThan(): void
  {
    self::assertSame('&gt;', $this->dec->quote('>'));
  }

  public function testQuoteLeavesDoubleQuoteUnescaped(): void
  {
    // ENT_NOQUOTES — double quotes must NOT be escaped in HTML body text.
    self::assertSame('"hello"', $this->dec->quote('"hello"'));
  }

  public function testQuoteLeavesPlainTextUnchanged(): void
  {
    self::assertSame('Hello World', $this->dec->quote('Hello World'));
  }

  public function testQuoteEscapesMultipleSpecials(): void
  {
    self::assertSame('&lt;b&gt;bold&lt;/b&gt;', $this->dec->quote('<b>bold</b>'));
  }

  // -------------------------------------------------------------------------
  // Decoration methods
  // -------------------------------------------------------------------------

  public function testBold(): void
  {
    self::assertSame('<b>x</b>', $this->exposer->bold_('x'));
  }

  public function testItalic(): void
  {
    self::assertSame('<i>x</i>', $this->exposer->italic_('x'));
  }

  public function testUnderline(): void
  {
    self::assertSame('<u>x</u>', $this->exposer->underline_('x'));
  }

  public function testStrikethrough(): void
  {
    self::assertSame('<s>x</s>', $this->exposer->strikethrough_('x'));
  }

  public function testSpoiler(): void
  {
    self::assertSame('<tg-spoiler>x</tg-spoiler>', $this->exposer->spoiler_('x'));
  }

  public function testBlockquote(): void
  {
    self::assertSame('<blockquote>x</blockquote>', $this->exposer->blockquote_('x'));
  }

  public function testExpandableBlockquote(): void
  {
    self::assertSame('<blockquote expandable>x</blockquote>', $this->exposer->expandableBlockquote_('x'));
  }

  public function testCode(): void
  {
    self::assertSame('<code>x</code>', $this->exposer->code_('x'));
  }

  public function testPre(): void
  {
    self::assertSame('<pre>x</pre>', $this->exposer->pre_('x'));
  }

  public function testPreLanguage(): void
  {
    self::assertSame(
      '<pre><code class="language-python">pass</code></pre>',
      $this->exposer->preLanguage_('pass', 'python'),
    );
  }

  public function testLink(): void
  {
    self::assertSame(
      '<a href="https://example.com">click</a>',
      $this->exposer->link_('click', 'https://example.com'),
    );
  }

  public function testCustomEmoji(): void
  {
    self::assertSame(
      '<tg-emoji emoji-id="999">😀</tg-emoji>',
      $this->exposer->customEmoji_('😀', '999'),
    );
  }

  public function testDateTimeWithFormat(): void
  {
    self::assertSame(
      '<tg-datetime unix-time="0" format="HH:mm">now</tg-datetime>',
      $this->exposer->dateTime_('now', 0, 'HH:mm'),
    );
  }

  public function testDateTimeWithoutFormat(): void
  {
    self::assertSame(
      '<tg-datetime unix-time="0">now</tg-datetime>',
      $this->exposer->dateTime_('now', 0, null),
    );
  }
}
