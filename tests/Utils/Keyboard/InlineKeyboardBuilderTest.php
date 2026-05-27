<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\Keyboard;

use Gruven\PhpBotGram\Filters\CallbackData;
use Gruven\PhpBotGram\Filters\CallbackPrefix;
use Gruven\PhpBotGram\Types\InlineKeyboardButton;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\KeyboardButton;
use Gruven\PhpBotGram\Utils\Keyboard\InlineKeyboardBuilder;
use Gruven\PhpBotGram\Utils\Keyboard\ReplyKeyboardBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Behavioural tests for {@see InlineKeyboardBuilder}.
 *
 * Mirrors upstream `tests/test_utils/test_keyboard.py` InlineKeyboard cases.
 *
 * Upstream skips
 * --------------
 * - `test_init` / `KeyboardBuilder(button_type=object)`: PHP has no public
 *   `KeyboardBuilder` base class instantiable with arbitrary button types —
 *   API divergence (a).
 * - `test_validate_button`, `test_validate_buttons`, `test_validate_row`,
 *   `test_validate_markup_*`, `test_validate_size`: these call internal
 *   `_validate_*` methods that are private in PHP — API divergence (a).
 * - `test_add_wo_max_width` (`builder.max_width = 0`): PHP does not expose a
 *   writable `$maxWidth` property — API divergence (a).
 * - `test_as_markup_preserves_icon_and_style`: `icon_custom_emoji_id` and
 *   `style` fields on buttons are stored but not yet plumbed through
 *   `button()` factory in the PHP port — phase scope deferral (b).
 * - `test_attach_not_builder` (attach a bare button): Python's `attach` raises
 *   `TypeError`; PHP raises `InvalidArgumentException` for wrong builder type;
 *   passing a non-builder is caught by type declarations — API divergence (a).
 *
 * @internal
 */
final class InlineKeyboardBuilderTest extends TestCase
{
  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  private static function btn(string $text, ?string $callbackData = null): InlineKeyboardButton
  {
    return new InlineKeyboardButton(text: $text, callbackData: $callbackData);
  }

  // ---------------------------------------------------------------------------
  // Empty builder
  // ---------------------------------------------------------------------------

  public function testEmptyBuilderProducesEmptyMarkup(): void
  {
    $builder = new InlineKeyboardBuilder();
    $markup = $builder->asMarkup();

    self::assertInstanceOf(InlineKeyboardMarkup::class, $markup);
    self::assertSame([], $markup->inlineKeyboard);
  }

  // ---------------------------------------------------------------------------
  // add()
  // ---------------------------------------------------------------------------

  public function testAddSingleButtonCreatesOneRow(): void
  {
    $builder = new InlineKeyboardBuilder();
    $builder->add(self::btn('A'));

    $markup = $builder->asMarkup();
    self::assertCount(1, $markup->inlineKeyboard);
    self::assertCount(1, $markup->inlineKeyboard[0]);
    self::assertSame('A', $markup->inlineKeyboard[0][0]->text);
  }

  public function testAddFlowsButtonsIntoRowsOfMaxWidth(): void
  {
    // MAX_WIDTH for InlineKeyboardBuilder is 8; 9 buttons → row of 8 + row of 1.
    $builder = new InlineKeyboardBuilder();
    $buttons = array_map(static fn(int $i): InlineKeyboardButton => self::btn((string)$i), range(1, 9));
    $builder->add(...$buttons);

    $markup = $builder->asMarkup();
    self::assertCount(2, $markup->inlineKeyboard);
    self::assertCount(8, $markup->inlineKeyboard[0]);
    self::assertCount(1, $markup->inlineKeyboard[1]);
  }

  public function testAddFillsIncompleteLastRow(): void
  {
    // First add creates a row with 3 buttons (MAX_WIDTH=8 so room for 5 more).
    $builder = new InlineKeyboardBuilder();
    $builder->add(self::btn('A'), self::btn('B'), self::btn('C'));
    $builder->add(self::btn('D'), self::btn('E'));

    $markup = $builder->asMarkup();
    // All 5 fit in one row — no new row needed.
    self::assertCount(1, $markup->inlineKeyboard);
    self::assertCount(5, $markup->inlineKeyboard[0]);
  }

  public function testAddRejectsWrongButtonType(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/KeyboardButton/');

    $builder = new InlineKeyboardBuilder();
    // @phpstan-ignore-next-line — intentional wrong-type button to verify runtime validation.
    $builder->add(new KeyboardButton(text: 'x'));
  }

  public function testAddMultipleButtonsEmptyNoop(): void
  {
    $builder = new InlineKeyboardBuilder();
    $result = $builder->add();

    self::assertSame($builder, $result);
    self::assertSame([], $builder->asMarkup()->inlineKeyboard);
  }

  // ---------------------------------------------------------------------------
  // row()
  // ---------------------------------------------------------------------------

  public function testRowAddsNewRow(): void
  {
    $builder = new InlineKeyboardBuilder();
    $builder->add(self::btn('A'));
    $builder->row([self::btn('B'), self::btn('C')]);

    $markup = $builder->asMarkup();
    self::assertCount(2, $markup->inlineKeyboard);
    self::assertSame('A', $markup->inlineKeyboard[0][0]->text);
    self::assertSame('B', $markup->inlineKeyboard[1][0]->text);
  }

  public function testRowWithWidthSplitsIntoSubrows(): void
  {
    $builder = new InlineKeyboardBuilder();
    $buttons = array_map(static fn(int $i): InlineKeyboardButton => self::btn((string)$i), range(1, 6));
    $builder->row($buttons, width: 2);

    $markup = $builder->asMarkup();
    self::assertCount(3, $markup->inlineKeyboard);

    foreach ($markup->inlineKeyboard as $row) {
      self::assertCount(2, $row);
    }
  }

  public function testRowWithNullWidthDefaultsToMaxWidth(): void
  {
    // With no explicit $width, row() must chunk by MAX_WIDTH=8.
    // 13 buttons → ceil(13/8) = 2 rows: [8, 5].
    $builder = new InlineKeyboardBuilder();
    $buttons = array_map(static fn(int $i): InlineKeyboardButton => self::btn((string)$i), range(1, 13));
    $builder->row($buttons); // width=null

    $markup = $builder->asMarkup();
    self::assertCount(2, $markup->inlineKeyboard);
    self::assertCount(8, $markup->inlineKeyboard[0]);
    self::assertCount(5, $markup->inlineKeyboard[1]);
  }

  public function testRowEmptyNoop(): void
  {
    $builder = new InlineKeyboardBuilder();
    $result = $builder->row([]);

    self::assertSame($builder, $result);
    self::assertSame([], $builder->asMarkup()->inlineKeyboard);
  }

  // ---------------------------------------------------------------------------
  // adjust()
  // ---------------------------------------------------------------------------

  public function testAdjustReshapesButtons(): void
  {
    // 5 buttons adjusted to rows of [2, 3] → row(2) + row(3).
    $builder = new InlineKeyboardBuilder();
    $buttons = array_map(static fn(int $i): InlineKeyboardButton => self::btn((string)$i), range(1, 5));
    $builder->add(...$buttons);
    $builder->adjust(2, 3);

    $markup = $builder->asMarkup();
    self::assertCount(2, $markup->inlineKeyboard);
    self::assertCount(2, $markup->inlineKeyboard[0]);
    self::assertCount(3, $markup->inlineKeyboard[1]);
  }

  public function testAdjustRepeatLastSizeForRemainingButtons(): void
  {
    // 7 buttons, sizes [2, 3] — last size (3) repeated:
    // row(2) + row(3) + row(2 remaining).
    $builder = new InlineKeyboardBuilder();
    $buttons = array_map(static fn(int $i): InlineKeyboardButton => self::btn((string)$i), range(1, 7));
    $builder->add(...$buttons);
    $builder->adjust(2, 3);

    $markup = $builder->asMarkup();
    // rows: [2], [3], [2]
    self::assertCount(3, $markup->inlineKeyboard);
    self::assertCount(2, $markup->inlineKeyboard[0]);
    self::assertCount(3, $markup->inlineKeyboard[1]);
    self::assertCount(2, $markup->inlineKeyboard[2]);
  }

  public function testAdjustRepeatingCyclesThroughSizes(): void
  {
    // 6 buttons, adjustRepeating(2, 3) cycles as 2, 3, 2, 3 …
    // buttons 1-2 → row(2), buttons 3-5 → row(3), button 6 → row(1 partial).
    $builder = new InlineKeyboardBuilder();
    $buttons = array_map(static fn(int $i): InlineKeyboardButton => self::btn((string)$i), range(1, 6));
    $builder->add(...$buttons);
    $builder->adjustRepeating(2, 3);

    $markup = $builder->asMarkup();
    // rows: [2], [3], [1]
    self::assertCount(3, $markup->inlineKeyboard);
    self::assertCount(2, $markup->inlineKeyboard[0]);
    self::assertCount(3, $markup->inlineKeyboard[1]);
    self::assertCount(1, $markup->inlineKeyboard[2]);
  }

  // ---------------------------------------------------------------------------
  // attach()
  // ---------------------------------------------------------------------------

  public function testAttachConcatenatesAnotherBuilder(): void
  {
    $a = new InlineKeyboardBuilder();
    $a->add(self::btn('A'));

    $b = new InlineKeyboardBuilder();
    $b->add(self::btn('B'));

    $a->attach($b);
    $markup = $a->asMarkup();

    self::assertCount(2, $markup->inlineKeyboard);
    self::assertSame('A', $markup->inlineKeyboard[0][0]->text);
    self::assertSame('B', $markup->inlineKeyboard[1][0]->text);
  }

  public function testAttachThrowsOnTypeMismatch(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/KeyboardButton/');

    $inline = new InlineKeyboardBuilder();
    $reply = new ReplyKeyboardBuilder();
    // @phpstan-ignore-next-line — intentional cross-type attach to verify runtime mismatch error.
    $inline->attach($reply);
  }

  // ---------------------------------------------------------------------------
  // asMarkup()
  // ---------------------------------------------------------------------------

  public function testAsMarkupReturnsInlineKeyboardMarkupInstance(): void
  {
    $builder = new InlineKeyboardBuilder();
    $builder->add(self::btn('X'));

    self::assertInstanceOf(InlineKeyboardMarkup::class, $builder->asMarkup());
  }

  // ---------------------------------------------------------------------------
  // button() — factory + CallbackData packing
  // ---------------------------------------------------------------------------

  public function testButtonMethodAddsButtonToMarkup(): void
  {
    $builder = new InlineKeyboardBuilder();
    $builder->button(text: 'Click me', callbackData: 'raw_data');

    $markup = $builder->asMarkup();
    self::assertCount(1, $markup->inlineKeyboard);
    self::assertSame('Click me', $markup->inlineKeyboard[0][0]->text);
    self::assertSame('raw_data', $markup->inlineKeyboard[0][0]->callbackData);
  }

  public function testButtonMethodPacksCallbackDataObject(): void
  {
    $builder = new InlineKeyboardBuilder();
    $cbData = new TestInlineCbData(id: 7, action: 'go');
    $builder->button(text: 'Go', callbackData: $cbData);

    $markup = $builder->asMarkup();
    self::assertSame('tst:7:go', $markup->inlineKeyboard[0][0]->callbackData);
  }

  // ---------------------------------------------------------------------------
  // fromMarkup() / copy()
  // ---------------------------------------------------------------------------

  public function testFromMarkupReconstructsBuilderState(): void
  {
    $original = new InlineKeyboardMarkup(inlineKeyboard: [[self::btn('A'), self::btn('B')]]);
    $builder = InlineKeyboardBuilder::fromMarkup($original);

    $markup = $builder->asMarkup();
    self::assertCount(1, $markup->inlineKeyboard);
    self::assertCount(2, $markup->inlineKeyboard[0]);
  }

  public function testCopyProducesIndependentBuilder(): void
  {
    $builder = new InlineKeyboardBuilder();
    $builder->add(self::btn('A'));

    $copy = $builder->copy();
    $copy->add(self::btn('B'));

    // Original unaffected.
    $origMarkup = $builder->asMarkup();
    self::assertCount(1, $origMarkup->inlineKeyboard);
    self::assertCount(1, $origMarkup->inlineKeyboard[0]);

    // Copy has two buttons.
    $copyMarkup = $copy->asMarkup();
    self::assertCount(1, $copyMarkup->inlineKeyboard);
    self::assertCount(2, $copyMarkup->inlineKeyboard[0]);
  }

  // ---------------------------------------------------------------------------
  // export() / buttons()
  // ---------------------------------------------------------------------------

  public function testExportReturnsDeepCopy(): void
  {
    $builder = new InlineKeyboardBuilder();
    $builder->add(self::btn('A'));

    $exported = $builder->export();
    // Mutating the exported array must not affect the internal state.
    $exported[0] = [];

    $markup = $builder->asMarkup();
    self::assertCount(1, $markup->inlineKeyboard[0]);
  }

  public function testButtonsGeneratorFlattensAllButtons(): void
  {
    $builder = new InlineKeyboardBuilder();
    $builder->row([self::btn('A'), self::btn('B')]);
    $builder->row([self::btn('C')]);

    $texts = [];

    foreach ($builder->buttons() as $btn) {
      $texts[] = $btn->text;
    }

    self::assertSame(['A', 'B', 'C'], $texts);
  }

  // ---------------------------------------------------------------------------
  // Method chaining (returns static)
  // ---------------------------------------------------------------------------

  public function testMethodsReturnSameBuilderInstance(): void
  {
    $builder = new InlineKeyboardBuilder();
    $result = $builder->add(self::btn('A'))->row([self::btn('B')])->adjust(1);

    self::assertSame($builder, $result);
  }

  // ---------------------------------------------------------------------------
  // MAX_BUTTONS enforcement
  // ---------------------------------------------------------------------------

  public function testAddRejectsMoreThanMaxButtonsTotal(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/Too many buttons \(got 101, max 100\)/');

    $builder = new InlineKeyboardBuilder();

    for ($i = 0; $i < 101; $i++) {
      $builder->add(self::btn("btn{$i}"));
    }
  }

  // ---------------------------------------------------------------------------
  // MAX_WIDTH enforcement in constructor (validateMarkup)
  // ---------------------------------------------------------------------------

  public function testConstructorRejectsRowExceedingMaxWidth(): void
  {
    // InlineKeyboardBuilder::MAX_WIDTH = 8; passing 9 buttons in one row must throw.
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/Row 0 has 9 buttons \(max 8\)/');

    $row = array_map(static fn(int $i): InlineKeyboardButton => self::btn("b{$i}"), range(1, 9));
    new InlineKeyboardBuilder([$row]);
  }

  // ---------------------------------------------------------------------------
  // row() explicit non-positive width
  // ---------------------------------------------------------------------------

  public function testRowWithZeroWidthThrows(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/row\(\) width must be > 0, got 0/');

    $builder = new InlineKeyboardBuilder();
    $builder->row([self::btn('A')], 0);
  }

  public function testRowWithNegativeWidthThrows(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/row\(\) width must be > 0, got -1/');

    $builder = new InlineKeyboardBuilder();
    $builder->row([self::btn('A')], -1);
  }
}

// ---------------------------------------------------------------------------
// Test fixtures
// ---------------------------------------------------------------------------

#[CallbackPrefix('tst')]
final class TestInlineCbData extends CallbackData
{
  public function __construct(
    public readonly int $id,
    public readonly string $action,
  ) {}
}
