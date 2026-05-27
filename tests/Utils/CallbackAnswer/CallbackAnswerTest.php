<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\CallbackAnswer;

use Gruven\PhpBotGram\Exceptions\CallbackAnswerException;
use Gruven\PhpBotGram\Utils\CallbackAnswer\CallbackAnswer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see CallbackAnswer}.
 *
 * Covers:
 *
 * - Constructor stores all supplied params correctly.
 * - Each guarded setter throws after `markAnswered()`.
 * - `disable()` convenience helper delegates to the `$disabled` setter.
 * - `isAnswered()` reflects the internal flag.
 * - `markAnswered()` is idempotent.
 * - The DTO starts in the mutable state (pre-mark, setters work fine).
 *
 * Port of upstream `tests/test_utils/test_callback_answer.py`.
 *
 * Upstream skips
 * --------------
 * - `test_str` (asserts `str(instance) == "CallbackAnswer(answered=False, …)"`):
 *   PHP does not implement `__toString()` on `CallbackAnswer` — API divergence (a).
 *
 * @internal
 */
final class CallbackAnswerTest extends TestCase
{
  // ---------------------------------------------------------------------------
  // Constructor / initial state
  // ---------------------------------------------------------------------------

  public function testConstructorStoresAllParams(): void
  {
    $ca = new CallbackAnswer(
      answered: false,
      disabled: true,
      text: 'hello',
      showAlert: true,
      url: 'https://t.me',
      cacheTime: 30,
    );

    self::assertFalse($ca->isAnswered());
    self::assertTrue($ca->disabled);
    self::assertSame('hello', $ca->text);
    self::assertTrue($ca->showAlert);
    self::assertSame('https://t.me', $ca->url);
    self::assertSame(30, $ca->cacheTime);
  }

  public function testDefaultValuesAreNull(): void
  {
    $ca = new CallbackAnswer(answered: false);

    self::assertFalse($ca->isAnswered());
    self::assertFalse($ca->disabled);
    self::assertNull($ca->text);
    self::assertNull($ca->showAlert);
    self::assertNull($ca->url);
    self::assertNull($ca->cacheTime);
  }

  // ---------------------------------------------------------------------------
  // Mutable-state setters (before markAnswered)
  // ---------------------------------------------------------------------------

  public function testSettersWorkBeforeMarkAnswered(): void
  {
    $ca = new CallbackAnswer(answered: false);

    $ca->disabled = true;
    $ca->text = 'updated';
    $ca->showAlert = false;
    $ca->url = 'https://example.com';
    $ca->cacheTime = 10;

    self::assertTrue($ca->disabled);
    self::assertSame('updated', $ca->text);
    self::assertFalse($ca->showAlert);
    self::assertSame('https://example.com', $ca->url);
    self::assertSame(10, $ca->cacheTime);
  }

  // ---------------------------------------------------------------------------
  // Guarded setters (after markAnswered)
  // ---------------------------------------------------------------------------

  public function testDisabledSetterThrowsAfterMarkAnswered(): void
  {
    $ca = new CallbackAnswer(answered: false);
    $ca->markAnswered();

    $this->expectException(CallbackAnswerException::class);
    $ca->disabled = true;
  }

  public function testTextSetterThrowsAfterMarkAnswered(): void
  {
    $ca = new CallbackAnswer(answered: false);
    $ca->markAnswered();

    $this->expectException(CallbackAnswerException::class);
    $ca->text = 'changed';
  }

  public function testShowAlertSetterThrowsAfterMarkAnswered(): void
  {
    $ca = new CallbackAnswer(answered: false);
    $ca->markAnswered();

    $this->expectException(CallbackAnswerException::class);
    $ca->showAlert = true;
  }

  public function testUrlSetterThrowsAfterMarkAnswered(): void
  {
    $ca = new CallbackAnswer(answered: false);
    $ca->markAnswered();

    $this->expectException(CallbackAnswerException::class);
    $ca->url = 'https://blocked.example';
  }

  public function testCacheTimeSetterThrowsAfterMarkAnswered(): void
  {
    $ca = new CallbackAnswer(answered: false);
    $ca->markAnswered();

    $this->expectException(CallbackAnswerException::class);
    $ca->cacheTime = 99;
  }

  // ---------------------------------------------------------------------------
  // disable() helper
  // ---------------------------------------------------------------------------

  public function testDisableHelperSetsDisabledTrue(): void
  {
    $ca = new CallbackAnswer(answered: false);
    self::assertFalse($ca->disabled);

    $ca->disable();

    self::assertTrue($ca->disabled);
  }

  public function testDisableHelperThrowsAfterMarkAnswered(): void
  {
    $ca = new CallbackAnswer(answered: false);
    $ca->markAnswered();

    $this->expectException(CallbackAnswerException::class);
    $ca->disable();
  }

  // ---------------------------------------------------------------------------
  // isAnswered() / markAnswered()
  // ---------------------------------------------------------------------------

  public function testIsAnsweredReturnsFalseInitially(): void
  {
    $ca = new CallbackAnswer(answered: false);

    self::assertFalse($ca->isAnswered());
  }

  public function testIsAnsweredReturnsTrueAfterMarkAnswered(): void
  {
    $ca = new CallbackAnswer(answered: false);
    $ca->markAnswered();

    self::assertTrue($ca->isAnswered());
  }

  public function testMarkAnsweredIsIdempotent(): void
  {
    $ca = new CallbackAnswer(answered: false);
    $ca->markAnswered();
    $ca->markAnswered(); // second call must not throw

    self::assertTrue($ca->isAnswered());
  }

  public function testConstructedWithAnsweredTrueThrowsImmediately(): void
  {
    // When $answered is passed as true (pre-mode), setters throw immediately.
    $ca = new CallbackAnswer(answered: true);

    $this->expectException(CallbackAnswerException::class);
    $ca->text = 'too late';
  }
}
