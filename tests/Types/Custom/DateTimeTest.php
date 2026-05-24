<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types\Custom;

use Gruven\PhpBotGram\Types\Custom\DateTime;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DateTimeTest extends TestCase
{
  public function testRoundTrip(): void
  {
    $ts = 1700000000;
    $dt = DateTime::fromTimestamp($ts);
    self::assertInstanceOf(DateTime::class, $dt);
    self::assertSame($ts, $dt->toTimestamp());
  }
}
