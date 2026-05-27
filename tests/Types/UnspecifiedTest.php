<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\Unspecified;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UnspecifiedTest extends TestCase
{
  public function testInstanceIsSingleton(): void
  {
    self::assertSame(Unspecified::instance(), Unspecified::instance());
  }
}
