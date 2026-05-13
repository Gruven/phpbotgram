<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter\Operation;

use Gruven\PhpBotGram\Utils\MagicFilter\AttrDict;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\GetAttributeOperation;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit coverage for `GetAttributeOperation` — the atomic "fetch attribute
 * by name" step that backs `F->foo` chain extensions.
 */
final class GetAttributeOperationTest extends TestCase
{
  public function testReadsPublicObjectProperty(): void
  {
    // Happy-path object access — the most common case in practice.
    $obj = new stdClass();
    $obj->name = 'aiogram';
    $op = new GetAttributeOperation('name');

    self::assertSame('aiogram', $op->resolve($obj, $obj));
  }

  public function testReadsDeclaredButNullProperty(): void
  {
    // A declared property whose value is `null` should be returned
    // verbatim — NOT mis-classified as "missing". This is the
    // `property_exists` (vs `isset`) distinction.
    $obj = new class {
      public ?string $name = null;
    };
    $op = new GetAttributeOperation('name');

    self::assertNull($op->resolve($obj, $obj));
  }

  public function testRejectsWhenObjectLacksTheProperty(): void
  {
    // Missing attribute → RejectOperations. The resolver loop in
    // MagicFilter then blanks the running value to null.
    $op = new GetAttributeOperation('missing');

    $this->expectException(RejectOperations::class);
    $op->resolve(new stdClass(), new stdClass());
  }

  public function testReadsArrayKey(): void
  {
    // Array subjects are honoured for AttrDict-style call sites.
    $op = new GetAttributeOperation('name');

    self::assertSame('aiogram', $op->resolve(['name' => 'aiogram'], null));
  }

  public function testRejectsOnMissingArrayKey(): void
  {
    $op = new GetAttributeOperation('missing');

    $this->expectException(RejectOperations::class);
    $op->resolve(['other' => 'value'], null);
  }

  public function testReadsViaArrayAccessInterface(): void
  {
    // ArrayAccess hybrids — like AttrDict — get probed via offsetExists/
    // offsetGet when the property-existence check fails.
    $dict = new AttrDict(['name' => 'aiogram']);
    $op = new GetAttributeOperation('name');

    self::assertSame('aiogram', $op->resolve($dict, $dict));
  }

  public function testRejectsOnScalarSubject(): void
  {
    // Non-object, non-array, non-ArrayAccess → reject. The chain
    // can't keep walking; the running value collapses to null.
    $op = new GetAttributeOperation('name');

    $this->expectException(RejectOperations::class);
    $op->resolve('a string', null);
  }
}
