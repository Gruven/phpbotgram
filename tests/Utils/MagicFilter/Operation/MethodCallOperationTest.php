<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter\Operation;

use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\MethodCallOperation;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit coverage for `MethodCallOperation` — the atomic
 * "invoke a named method on the value" step that backs `F->lower()`
 * chain extensions (PHP-side collapse of upstream's `__getattr__` then
 * `__call__` pair).
 */
final class MethodCallOperationTest extends TestCase
{
  public function testInvokesNamedMethodWithoutArgs(): void
  {
    // `F->shout()` → MethodCallOperation('shout', [], []).
    $obj = new class {
      public function shout(): string
      {
        return 'OY';
      }
    };
    $op = new MethodCallOperation('shout', [], []);

    self::assertSame('OY', $op->resolve($obj, $obj));
  }

  public function testForwardsPositionalArgs(): void
  {
    // Positional arguments unpack via PHP's `...$args` spread.
    $obj = new class {
      public function sum(int $a, int $b): int
      {
        return $a + $b;
      }
    };
    $op = new MethodCallOperation('sum', [3, 4], []);

    self::assertSame(7, $op->resolve($obj, $obj));
  }

  public function testForwardsNamedArgsViaSpread(): void
  {
    // Named arguments survive the args/kwargs split.
    $obj = new class {
      public function greet(string $name, string $greeting = 'hi'): string
      {
        return "{$greeting}, {$name}";
      }
    };
    $op = new MethodCallOperation('greet', ['world'], ['greeting' => 'hello']);

    self::assertSame('hello, world', $op->resolve($obj, $obj));
  }

  public function testRejectsWhenValueIsNotAnObject(): void
  {
    // Method call requires an object subject. Scalars / arrays reject.
    $op = new MethodCallOperation('shout', [], []);

    $this->expectException(RejectOperations::class);
    $op->resolve('plain string', null);
  }

  public function testRejectsWhenMethodIsMissingAndNoMagicCallExists(): void
  {
    // Unknown method on a vanilla object — reject.
    $op = new MethodCallOperation('missing', [], []);

    $this->expectException(RejectOperations::class);
    $op->resolve(new stdClass(), null);
  }

  public function testHonoursMagicCallDispatch(): void
  {
    // Classes that implement `__call` handle unknown method names via
    // magic dispatch — we must not reject preemptively.
    $obj = new class {
      /** @param array<int, mixed> $arguments */
      public function __call(string $name, array $arguments): string
      {
        return "{$name}:" . count($arguments);
      }
    };
    $op = new MethodCallOperation('whatever', [1, 2, 3], []);

    self::assertSame('whatever:3', $op->resolve($obj, $obj));
  }
}
