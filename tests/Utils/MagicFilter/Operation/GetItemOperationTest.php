<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter\Operation;

use Gruven\PhpBotGram\Utils\MagicFilter\AttrDict;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\SwitchModeToAll;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\SwitchModeToAny;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\GetItemOperation;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for `GetItemOperation` — subscript access including the
 * two wildcard sentinels that trigger fan-out resolution.
 *
 * @internal
 */
final class GetItemOperationTest extends TestCase
{
  public function testReadsArrayValueByStringKey(): void
  {
    $op = new GetItemOperation('name');

    self::assertSame('aiogram', $op->resolve(['name' => 'aiogram'], null));
  }

  public function testReadsArrayValueByIntKey(): void
  {
    $op = new GetItemOperation(1);

    self::assertSame('b', $op->resolve(['a', 'b', 'c'], null));
  }

  public function testReadsArrayAccessValue(): void
  {
    $op = new GetItemOperation('name');
    $dict = new AttrDict(['name' => 'aiogram']);

    self::assertSame('aiogram', $op->resolve($dict, $dict));
  }

  public function testReadsStringByIntegerIndex(): void
  {
    // Pythonic string indexing — `value[0]` returns the byte.
    $op = new GetItemOperation(0);

    self::assertSame('h', $op->resolve('hello', null));
  }

  public function testRejectsOnMissingArrayKey(): void
  {
    $op = new GetItemOperation('missing');

    $this->expectException(RejectOperations::class);
    $op->resolve(['name' => 'x'], null);
  }

  public function testRejectsOnUnsubscriptableValue(): void
  {
    $op = new GetItemOperation('x');

    $this->expectException(RejectOperations::class);
    $op->resolve(42, null);
  }

  public function testWildcardAnyRaisesSwitchModeToAny(): void
  {
    // The fan-out + ANY sentinel: resolver intercepts the exception and
    // iterates the remaining chain over each element.
    $op = new GetItemOperation(MagicFilter::WILDCARD_ANY);

    $this->expectException(SwitchModeToAny::class);
    $op->resolve([1, 2, 3], null);
  }

  public function testWildcardAllRaisesSwitchModeToAll(): void
  {
    $op = new GetItemOperation(MagicFilter::WILDCARD_ALL);

    $this->expectException(SwitchModeToAll::class);
    $op->resolve([1, 2, 3], null);
  }
}
