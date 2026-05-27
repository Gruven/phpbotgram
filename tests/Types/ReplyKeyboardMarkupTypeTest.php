<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\KeyboardButton;
use Gruven\PhpBotGram\Types\ReplyKeyboardMarkup;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_types/test_reply_keyboard_markup.py
 *
 * Upstream skips:
 *   - Pydantic model_validate / model_dump — API divergence (a).
 *   - ReplyKeyboardBuilder test (Python-specific builder) — API divergence (a):
 *     PHP equivalent is Utils\Keyboard\ReplyKeyboardBuilder; tested separately.
 *
 * @internal
 */
final class ReplyKeyboardMarkupTypeTest extends TestCase
{
  // ── construction ─────────────────────────────────────────────────────────────

  public function testRequiredKeyboardOnly(): void
  {
    $markup = new ReplyKeyboardMarkup(keyboard: [[new KeyboardButton(text: 'Yes'), new KeyboardButton(text: 'No')]]);
    self::assertCount(1, $markup->keyboard);
    self::assertCount(2, $markup->keyboard[0]);
    self::assertNull($markup->resizeKeyboard);
    self::assertNull($markup->oneTimeKeyboard);
    self::assertNull($markup->inputFieldPlaceholder);
    self::assertNull($markup->selective);
  }

  public function testWithAllOptions(): void
  {
    $markup = new ReplyKeyboardMarkup(
      keyboard: [[new KeyboardButton(text: 'A')]],
      isPersistent: true,
      resizeKeyboard: true,
      oneTimeKeyboard: false,
      inputFieldPlaceholder: 'Type here...',
      selective: false,
    );
    self::assertTrue($markup->isPersistent);
    self::assertTrue($markup->resizeKeyboard);
    self::assertFalse($markup->oneTimeKeyboard);
    self::assertSame('Type here...', $markup->inputFieldPlaceholder);
    self::assertFalse($markup->selective);
  }

  public function testMultiRowKeyboard(): void
  {
    $markup = new ReplyKeyboardMarkup(
      keyboard: [
        [new KeyboardButton(text: 'Row1A'), new KeyboardButton(text: 'Row1B')],
        [new KeyboardButton(text: 'Row2A')],
      ],
    );
    self::assertCount(2, $markup->keyboard);
    self::assertCount(2, $markup->keyboard[0]);
    self::assertCount(1, $markup->keyboard[1]);
    self::assertSame('Row2A', $markup->keyboard[1][0]->text);
  }

  public function testEmptyKeyboard(): void
  {
    $markup = new ReplyKeyboardMarkup(keyboard: []);
    self::assertCount(0, $markup->keyboard);
  }
}
