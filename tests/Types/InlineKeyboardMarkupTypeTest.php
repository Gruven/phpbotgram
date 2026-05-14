<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\InlineKeyboardButton;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_types/test_inline_keyboard_markup.py
 *
 * Upstream skips:
 *   - Pydantic model_validate / model_dump — API divergence (a).
 *   - builder pattern tests (aiogram's InlineKeyboardBuilder) — API divergence (a):
 *     PHP exposes Utils\Keyboard\InlineKeyboardBuilder; that is tested in
 *     its own test file.
 */
final class InlineKeyboardMarkupTypeTest extends TestCase
{
  // ── construction ─────────────────────────────────────────────────────────────

  public function testSingleRowSingleButton(): void
  {
    $btn = new InlineKeyboardButton(text: 'Click me', callbackData: 'cb1');
    $markup = new InlineKeyboardMarkup(inlineKeyboard: [[$btn]]);
    self::assertCount(1, $markup->inlineKeyboard);
    self::assertCount(1, $markup->inlineKeyboard[0]);
    self::assertSame('Click me', $markup->inlineKeyboard[0][0]->text);
  }

  public function testMultipleRowsAndButtons(): void
  {
    $markup = new InlineKeyboardMarkup(
      inlineKeyboard: [
        [
          new InlineKeyboardButton(text: 'Row1 Col1', callbackData: 'r1c1'),
          new InlineKeyboardButton(text: 'Row1 Col2', callbackData: 'r1c2'),
        ],
        [
          new InlineKeyboardButton(text: 'Row2 Col1', url: 'https://example.com'),
        ],
      ],
    );
    self::assertCount(2, $markup->inlineKeyboard);
    self::assertCount(2, $markup->inlineKeyboard[0]);
    self::assertCount(1, $markup->inlineKeyboard[1]);
    self::assertSame('Row2 Col1', $markup->inlineKeyboard[1][0]->text);
    self::assertSame('https://example.com', $markup->inlineKeyboard[1][0]->url);
  }

  public function testEmptyKeyboard(): void
  {
    $markup = new InlineKeyboardMarkup(inlineKeyboard: []);
    self::assertCount(0, $markup->inlineKeyboard);
  }

  public function testButtonWithUrlAndCallbackData(): void
  {
    $btn = new InlineKeyboardButton(text: 'Visit', url: 'https://t.me', callbackData: 'cb');
    $markup = new InlineKeyboardMarkup(inlineKeyboard: [[$btn]]);
    $first = $markup->inlineKeyboard[0][0];
    self::assertSame('Visit', $first->text);
    self::assertSame('https://t.me', $first->url);
    self::assertSame('cb', $first->callbackData);
  }
}
