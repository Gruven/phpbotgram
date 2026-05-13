<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\Logic;

use Closure;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Filters\Logic\InvertFilter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\TelegramObject;
use PHPUnit\Framework\TestCase;

final class InvertFilterTest extends TestCase
{
  public function testWrappedTrueInvertsToFalse(): void
  {
    // The simplest negation case: accept becomes reject. Mirrors
    // upstream `not (await self.target(*args, **kwargs))`.
    $filter = new InvertFilter($this->filter(static fn(): bool => true));

    self::assertFalse($filter($this->event()));
  }

  public function testWrappedFalseInvertsToTrue(): void
  {
    // The complement: reject becomes accept. The combinator never
    // contributes its own kwargs — it can only flip a boolean.
    $filter = new InvertFilter($this->filter(static fn(): bool => false));

    self::assertTrue($filter($this->event()));
  }

  public function testWrappedArrayInvertsToFalse(): void
  {
    // PHP port deviates intentionally from naive Python truthiness here:
    // in Python `not {'k': 1}` is `False` and `not {}` is `True`, but our
    // filters never legitimately return an empty array (the dispatcher
    // would treat that as rejection anyway via HandlerObject::check).
    // We collapse both array shapes to "accept" upstream → false here.
    $filter = new InvertFilter($this->filter(static fn(): array => ['match' => 'data']));

    self::assertFalse($filter($this->event()));
  }

  /**
   * @param Closure(TelegramObject, array<string, mixed>): (array<string, mixed>|bool) $vote
   */
  private function filter(Closure $vote): Filter
  {
    return new class ($vote) extends Filter {
      /** @param Closure(TelegramObject, array<string, mixed>): (array<string, mixed>|bool) $vote */
      public function __construct(private readonly Closure $vote) {}

      public function __invoke(TelegramObject $event, array $kwargs = []): array|bool
      {
        return ($this->vote)($event, $kwargs);
      }
    };
  }

  private function event(): TelegramObject
  {
    return new Chat(id: 1, type: 'private');
  }
}
