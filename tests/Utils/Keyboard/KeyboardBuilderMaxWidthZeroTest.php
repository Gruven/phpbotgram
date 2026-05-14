<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\Keyboard;

use Gruven\PhpBotGram\Types\InlineKeyboardButton;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Utils\Keyboard\InlineKeyboardBuilder;
use Gruven\PhpBotGram\Utils\Keyboard\KeyboardBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for {@see KeyboardBuilder::add()} when `MAX_WIDTH === 0`.
 *
 * Verifies upstream `keyboard.py` semantics: when `max_width == 0` each
 * `add()` call appends a **new row**, not the last row (no row-fill).
 */

// ---------------------------------------------------------------------------
// Fixture: builder subclass with MAX_WIDTH = 0
// ---------------------------------------------------------------------------

/**
 * @extends KeyboardBuilder<InlineKeyboardButton>
 */
final class ZeroWidthBuilder extends KeyboardBuilder
{
  public const int MAX_WIDTH = 0;

  public function __construct()
  {
    parent::__construct(InlineKeyboardButton::class);
  }

  public function asMarkup(): InlineKeyboardMarkup
  {
    return new InlineKeyboardMarkup(inlineKeyboard: $this->export());
  }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

final class KeyboardBuilderMaxWidthZeroTest extends TestCase
{
  private static function btn(string $text): InlineKeyboardButton
  {
    return new InlineKeyboardButton(text: $text);
  }

  public function testAddWithMaxWidthZeroAppendsSeparateRows(): void
  {
    // Two consecutive add() calls with MAX_WIDTH=0 must produce two rows —
    // not a single concatenated row (upstream parity).
    $builder = new ZeroWidthBuilder();
    $builder->add(self::btn('A'), self::btn('B'));
    $builder->add(self::btn('C'));

    $markup = $builder->asMarkup();

    self::assertCount(2, $markup->inlineKeyboard, 'Expected 2 rows, got ' . count($markup->inlineKeyboard));
    self::assertCount(2, $markup->inlineKeyboard[0]);
    self::assertCount(1, $markup->inlineKeyboard[1]);
    self::assertSame('A', $markup->inlineKeyboard[0][0]->text);
    self::assertSame('B', $markup->inlineKeyboard[0][1]->text);
    self::assertSame('C', $markup->inlineKeyboard[1][0]->text);
  }

  public function testAddWithMaxWidthZeroSingleCallProducesOneRow(): void
  {
    // A single add() with multiple buttons still produces exactly one row.
    $builder = new ZeroWidthBuilder();
    $builder->add(self::btn('X'), self::btn('Y'), self::btn('Z'));

    $markup = $builder->asMarkup();

    self::assertCount(1, $markup->inlineKeyboard);
    self::assertCount(3, $markup->inlineKeyboard[0]);
  }

  public function testAddWithMaxWidthZeroThreeCallsProducesThreeRows(): void
  {
    // Each add() call = one new row, regardless of content.
    $builder = new ZeroWidthBuilder();
    $builder->add(self::btn('1'));
    $builder->add(self::btn('2'));
    $builder->add(self::btn('3'));

    $markup = $builder->asMarkup();

    self::assertCount(3, $markup->inlineKeyboard);
  }

  public function testConcreteBuilderWithPositiveMaxWidthStillFillsLastRow(): void
  {
    // Verify the positive-MAX_WIDTH path is unaffected — regression guard.
    $builder = new InlineKeyboardBuilder(); // MAX_WIDTH = 8

    $builder->add(self::btn('A'), self::btn('B'), self::btn('C'));
    $builder->add(self::btn('D'), self::btn('E'));

    $markup = $builder->asMarkup();

    // All 5 buttons fit in one row (3 existing + 2 fill = 5, well under 8).
    self::assertCount(1, $markup->inlineKeyboard);
    self::assertCount(5, $markup->inlineKeyboard[0]);
  }
}
