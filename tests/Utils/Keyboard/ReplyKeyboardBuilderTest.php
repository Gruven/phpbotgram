<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\Keyboard;

use Gruven\PhpBotGram\Types\InlineKeyboardButton;
use Gruven\PhpBotGram\Types\KeyboardButton;
use Gruven\PhpBotGram\Types\ReplyKeyboardMarkup;
use Gruven\PhpBotGram\Utils\Keyboard\InlineKeyboardBuilder;
use Gruven\PhpBotGram\Utils\Keyboard\ReplyKeyboardBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Behavioural tests for {@see ReplyKeyboardBuilder}.
 *
 * Mirrors upstream `tests/test_utils/test_keyboard.py` ReplyKeyboard cases.
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
final class ReplyKeyboardBuilderTest extends TestCase
{
  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  private static function btn(string $text): KeyboardButton
  {
    return new KeyboardButton(text: $text);
  }

  // ---------------------------------------------------------------------------
  // Empty builder
  // ---------------------------------------------------------------------------

  public function testEmptyBuilderProducesEmptyMarkup(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $markup = $builder->asMarkup();

    self::assertInstanceOf(ReplyKeyboardMarkup::class, $markup);
    self::assertSame([], $markup->keyboard);
  }

  // ---------------------------------------------------------------------------
  // add()
  // ---------------------------------------------------------------------------

  public function testAddSingleButtonCreatesOneRow(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $builder->add(self::btn('Hi'));

    $markup = $builder->asMarkup();
    self::assertCount(1, $markup->keyboard);
    self::assertCount(1, $markup->keyboard[0]);
    self::assertSame('Hi', $markup->keyboard[0][0]->text);
  }

  public function testAddFlowsButtonsIntoRowsOfMaxWidth(): void
  {
    // MAX_WIDTH for ReplyKeyboardBuilder is 10 (upstream parity);
    // 11 buttons → row of 10 + row of 1.
    $builder = new ReplyKeyboardBuilder();
    $buttons = array_map(static fn(int $i): KeyboardButton => self::btn((string)$i), range(1, 11));
    $builder->add(...$buttons);

    $markup = $builder->asMarkup();
    self::assertCount(2, $markup->keyboard);
    self::assertCount(10, $markup->keyboard[0]);
    self::assertCount(1, $markup->keyboard[1]);
  }

  public function testAddFillsIncompleteLastRow(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $builder->add(self::btn('A'), self::btn('B'));
    $builder->add(self::btn('C'));

    $markup = $builder->asMarkup();
    self::assertCount(1, $markup->keyboard);
    self::assertCount(3, $markup->keyboard[0]);
  }

  public function testAddRejectsWrongButtonType(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/InlineKeyboardButton/');

    $builder = new ReplyKeyboardBuilder();
    // @phpstan-ignore-next-line — intentional wrong-type button to verify runtime validation.
    $builder->add(new InlineKeyboardButton(text: 'x'));
  }

  // ---------------------------------------------------------------------------
  // row()
  // ---------------------------------------------------------------------------

  public function testRowAddsNewRow(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $builder->add(self::btn('A'));
    $builder->row([self::btn('B'), self::btn('C')]);

    $markup = $builder->asMarkup();
    self::assertCount(2, $markup->keyboard);
    self::assertSame('A', $markup->keyboard[0][0]->text);
    self::assertSame('B', $markup->keyboard[1][0]->text);
  }

  public function testRowWithWidthSplitsIntoSubrows(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $buttons = array_map(static fn(int $i): KeyboardButton => self::btn((string)$i), range(1, 4));
    $builder->row($buttons, width: 2);

    $markup = $builder->asMarkup();
    self::assertCount(2, $markup->keyboard);
    self::assertCount(2, $markup->keyboard[0]);
    self::assertCount(2, $markup->keyboard[1]);
  }

  // ---------------------------------------------------------------------------
  // adjust()
  // ---------------------------------------------------------------------------

  public function testAdjustReshapesButtons(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $buttons = array_map(static fn(int $i): KeyboardButton => self::btn((string)$i), range(1, 5));
    $builder->add(...$buttons);
    $builder->adjust(2, 3);

    $markup = $builder->asMarkup();
    self::assertCount(2, $markup->keyboard);
    self::assertCount(2, $markup->keyboard[0]);
    self::assertCount(3, $markup->keyboard[1]);
  }

  public function testAdjustRepeatLastSizeForRemainingButtons(): void
  {
    // 8 buttons, sizes [3, 2] — last size (2) repeated:
    // row(3) + row(2) + row(2) + row(1 remainder).
    $builder = new ReplyKeyboardBuilder();
    $buttons = array_map(static fn(int $i): KeyboardButton => self::btn((string)$i), range(1, 8));
    $builder->add(...$buttons);
    $builder->adjust(3, 2);

    $markup = $builder->asMarkup();
    // rows: [3], [2], [2], [1]
    self::assertCount(4, $markup->keyboard);
    self::assertCount(3, $markup->keyboard[0]);
    self::assertCount(2, $markup->keyboard[1]);
    self::assertCount(2, $markup->keyboard[2]);
    self::assertCount(1, $markup->keyboard[3]);
  }

  public function testAdjustRepeatingCyclesThroughSizes(): void
  {
    // 6 buttons, adjustRepeating(1, 2) cycles: 1, 2, 1, 2 ...
    // buttons [1], [2,3], [4], [5,6]
    $builder = new ReplyKeyboardBuilder();
    $buttons = array_map(static fn(int $i): KeyboardButton => self::btn((string)$i), range(1, 6));
    $builder->add(...$buttons);
    $builder->adjustRepeating(1, 2);

    $markup = $builder->asMarkup();
    self::assertCount(4, $markup->keyboard);
    self::assertCount(1, $markup->keyboard[0]);
    self::assertCount(2, $markup->keyboard[1]);
    self::assertCount(1, $markup->keyboard[2]);
    self::assertCount(2, $markup->keyboard[3]);
  }

  // ---------------------------------------------------------------------------
  // attach()
  // ---------------------------------------------------------------------------

  public function testAttachConcatenatesAnotherBuilder(): void
  {
    $a = new ReplyKeyboardBuilder();
    $a->add(self::btn('A'));

    $b = new ReplyKeyboardBuilder();
    $b->add(self::btn('B'));

    $a->attach($b);
    $markup = $a->asMarkup();

    self::assertCount(2, $markup->keyboard);
    self::assertSame('A', $markup->keyboard[0][0]->text);
    self::assertSame('B', $markup->keyboard[1][0]->text);
  }

  public function testAttachThrowsOnTypeMismatch(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/InlineKeyboardButton/');

    $reply = new ReplyKeyboardBuilder();
    $inline = new InlineKeyboardBuilder();
    // @phpstan-ignore-next-line — intentional cross-type attach to verify runtime mismatch error.
    $reply->attach($inline);
  }

  // ---------------------------------------------------------------------------
  // asMarkup() — options
  // ---------------------------------------------------------------------------

  public function testAsMarkupReturnsReplyKeyboardMarkupInstance(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $builder->add(self::btn('X'));

    self::assertInstanceOf(ReplyKeyboardMarkup::class, $builder->asMarkup());
  }

  public function testAsMarkupForwardsOptions(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $builder->add(self::btn('Y'));

    $markup = $builder->asMarkup(
      resizeKeyboard: true,
      oneTimeKeyboard: true,
      inputFieldPlaceholder: 'Type here',
    );

    self::assertTrue($markup->resizeKeyboard);
    self::assertTrue($markup->oneTimeKeyboard);
    self::assertSame('Type here', $markup->inputFieldPlaceholder);
  }

  // ---------------------------------------------------------------------------
  // button() — factory
  // ---------------------------------------------------------------------------

  public function testButtonMethodAddsButtonToMarkup(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $builder->button(text: 'Send contact', requestContact: true);

    $markup = $builder->asMarkup();
    self::assertCount(1, $markup->keyboard);
    self::assertSame('Send contact', $markup->keyboard[0][0]->text);
    self::assertTrue($markup->keyboard[0][0]->requestContact);
  }

  // ---------------------------------------------------------------------------
  // fromMarkup() / copy()
  // ---------------------------------------------------------------------------

  public function testFromMarkupReconstructsBuilderState(): void
  {
    $original = new ReplyKeyboardMarkup(keyboard: [[self::btn('A'), self::btn('B')]]);
    $builder = ReplyKeyboardBuilder::fromMarkup($original);

    $markup = $builder->asMarkup();
    self::assertCount(1, $markup->keyboard);
    self::assertCount(2, $markup->keyboard[0]);
  }

  public function testCopyProducesIndependentBuilder(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $builder->add(self::btn('A'));

    $copy = $builder->copy();
    $copy->add(self::btn('B'));

    // Original unaffected.
    $origMarkup = $builder->asMarkup();
    self::assertCount(1, $origMarkup->keyboard);
    self::assertCount(1, $origMarkup->keyboard[0]);

    // Copy has two buttons.
    $copyMarkup = $copy->asMarkup();
    self::assertCount(1, $copyMarkup->keyboard);
    self::assertCount(2, $copyMarkup->keyboard[0]);
  }

  // ---------------------------------------------------------------------------
  // export() / buttons()
  // ---------------------------------------------------------------------------

  public function testExportReturnsDeepCopy(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $builder->add(self::btn('A'));

    $exported = $builder->export();
    $exported[0] = [];

    $markup = $builder->asMarkup();
    self::assertCount(1, $markup->keyboard[0]);
  }

  public function testButtonsGeneratorFlattensAllButtons(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $builder->row([self::btn('A'), self::btn('B')]);
    $builder->row([self::btn('C')]);

    $texts = [];

    foreach ($builder->buttons() as $btn) {
      $texts[] = $btn->text;
    }

    self::assertSame(['A', 'B', 'C'], $texts);
  }

  // ---------------------------------------------------------------------------
  // Method chaining
  // ---------------------------------------------------------------------------

  public function testMethodsReturnSameBuilderInstance(): void
  {
    $builder = new ReplyKeyboardBuilder();
    $result = $builder->add(self::btn('A'))->row([self::btn('B')])->adjust(1);

    self::assertSame($builder, $result);
  }

  // ---------------------------------------------------------------------------
  // MAX_BUTTONS enforcement
  // ---------------------------------------------------------------------------

  public function testAddRejectsMoreThanMaxButtonsTotal(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/Too many buttons \(got 301, max 300\)/');

    $builder = new ReplyKeyboardBuilder();

    for ($i = 0; $i < 301; $i++) {
      $builder->add(self::btn("btn{$i}"));
    }
  }

  // ---------------------------------------------------------------------------
  // MAX_WIDTH enforcement in constructor (validateMarkup)
  // ---------------------------------------------------------------------------

  public function testConstructorRejectsRowExceedingMaxWidth(): void
  {
    // ReplyKeyboardBuilder::MAX_WIDTH = 10; passing 11 buttons in one row must throw.
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/Row 0 has 11 buttons \(max 10\)/');

    $row = array_map(static fn(int $i): KeyboardButton => self::btn("b{$i}"), range(1, 11));
    new ReplyKeyboardBuilder([$row]);
  }
}
