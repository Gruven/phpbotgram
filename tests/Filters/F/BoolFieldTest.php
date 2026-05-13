<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\F;

use Gruven\PhpBotGram\Filters\F\BoolField;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `BoolField` — typed wrapper for boolean Telegram fields
 * (`User::$isPremium`, `Message::$isTopicMessage`, …). Exposes only the
 * two truth-value comparators (`isTrue`, `isFalse`) so call sites stay
 * readable.
 */
final class BoolFieldTest extends TestCase
{
  public function testIsTrueAcceptsTrueRejectsFalse(): void
  {
    // `F->isBot->isTrue()` accepts a bot user and rejects a human.
    $filter = (new BoolField(MagicFilter::root()->isBot))->isTrue();

    self::assertTrue($filter($this->user(isBot: true)));
    self::assertFalse($filter($this->user(isBot: false)));
  }

  public function testIsFalseAcceptsFalseRejectsTrue(): void
  {
    $filter = (new BoolField(MagicFilter::root()->isBot))->isFalse();

    self::assertTrue($filter($this->user(isBot: false)));
    self::assertFalse($filter($this->user(isBot: true)));
  }

  private function user(bool $isBot): User
  {
    return new User(id: 1, isBot: $isBot, firstName: 'Alice');
  }
}
