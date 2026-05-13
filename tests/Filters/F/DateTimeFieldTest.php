<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\F;

use Gruven\PhpBotGram\Filters\F\DateTimeField;
use Gruven\PhpBotGram\Filters\Logic\AndFilter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `DateTimeField` — typed wrapper for `\DateTime`-valued
 * Telegram fields (`Message::$date`, `Message::$editDate`, …). Provides
 * temporal comparator helpers (`before`, `after`, `between`).
 */
final class DateTimeFieldTest extends TestCase
{
  public function testBeforeAcceptsEarlierDates(): void
  {
    // `before($when)` accepts dates strictly before `$when`. The
    // underlying chain uses MagicFilter's `lt` comparator on the
    // running DateTime value.
    $filter = (new DateTimeField(MagicFilter::root()->date))
      ->before(new DateTime('2024-01-01'));

    self::assertTrue($filter($this->message(date: new DateTime('2023-12-31'))));
    self::assertFalse($filter($this->message(date: new DateTime('2024-01-02'))));
  }

  public function testAfterAcceptsLaterDates(): void
  {
    $filter = (new DateTimeField(MagicFilter::root()->date))
      ->after(new DateTime('2024-01-01'));

    self::assertTrue($filter($this->message(date: new DateTime('2024-01-02'))));
    self::assertFalse($filter($this->message(date: new DateTime('2023-12-31'))));
  }

  public function testBetweenReturnsAndFilterComposingGteAndLte(): void
  {
    $filter = (new DateTimeField(MagicFilter::root()->date))
      ->between(new DateTime('2024-01-01'), new DateTime('2024-12-31'));

    self::assertInstanceOf(AndFilter::class, $filter);
    self::assertCount(2, $filter->targets);
  }

  public function testBetweenAcceptsInclusiveBoundsAndRejectsOutside(): void
  {
    // Inclusive range — `between($from, $to)` composes gte+lte so the
    // bounding dates themselves accept.
    $filter = (new DateTimeField(MagicFilter::root()->date))
      ->between(new DateTime('2024-01-01'), new DateTime('2024-12-31'));

    self::assertTrue($filter($this->message(date: new DateTime('2024-01-01'))));
    self::assertTrue($filter($this->message(date: new DateTime('2024-06-15'))));
    self::assertTrue($filter($this->message(date: new DateTime('2024-12-31'))));
    self::assertFalse($filter($this->message(date: new DateTime('2023-12-31'))));
    self::assertFalse($filter($this->message(date: new DateTime('2025-01-01'))));
  }

  private function message(DateTime $date): Message
  {
    return new Message(
      messageId: 1,
      date: $date,
      chat: new Chat(id: 1, type: 'private'),
    );
  }
}
