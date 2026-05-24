<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Flags;

use Gruven\PhpBotGram\Dispatcher\Flags\Flag;
use Gruven\PhpBotGram\Dispatcher\Flags\FlagDecorator;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
final class FlagDecoratorTest extends TestCase
{
  protected function setUp(): void
  {
    // Storage is process-static — reset between tests so we don't accidentally
    // see leftovers from a previous test's attach() call.
    FlagDecorator::reset();
  }

  public function testAttachStoresFlagOnTargetAndAttachedReturnsIt(): void
  {
    $closure = static fn(): bool => true;
    $flag = new Flag('admin_only');

    FlagDecorator::attach($closure, $flag);

    self::assertSame([$flag], FlagDecorator::attached($closure));
  }

  public function testAttachMultipleFlagsGrowsListInOrder(): void
  {
    // Attach order must be preserved — middleware that iterates flags often
    // cares about precedence (e.g. first-wins on duplicate name keys when
    // merged by `Flags::extractFlags`).
    $closure = static fn(): bool => true;
    $a = new Flag('admin_only');
    $b = new Flag('throttle', 5);
    $c = new Flag('chat_action', 'typing');

    FlagDecorator::attach($closure, $a);
    FlagDecorator::attach($closure, $b);
    FlagDecorator::attach($closure, $c);

    self::assertSame([$a, $b, $c], FlagDecorator::attached($closure));
  }

  public function testAttachKeepsStoragePerTargetIndependent(): void
  {
    // Two closures = two WeakMap keys. Verify cross-contamination doesn't
    // happen — otherwise attaching a flag to one handler would leak onto an
    // unrelated handler.
    $closureA = static fn(): bool => true;
    $closureB = static fn(): bool => true;

    FlagDecorator::attach($closureA, new Flag('only_a'));
    FlagDecorator::attach($closureB, new Flag('only_b'));

    $attachedA = FlagDecorator::attached($closureA);
    $attachedB = FlagDecorator::attached($closureB);

    self::assertCount(1, $attachedA);
    self::assertSame('only_a', $attachedA[0]->name);

    self::assertCount(1, $attachedB);
    self::assertSame('only_b', $attachedB[0]->name);
  }

  public function testAttachReturnsTargetUnchangedForDecoratorChaining(): void
  {
    // Decorator-style usage: `$cb = FlagDecorator::attach($cb, new Flag(...));`
    // The returned reference must be the very same object — otherwise the
    // caller can't keep using the original handler after wiring flags.
    $closure = static fn(): bool => true;

    $returned = FlagDecorator::attach($closure, new Flag('x'));

    self::assertSame($closure, $returned);
  }

  public function testAttachAcceptsArbitraryObjectsNotJustClosures(): void
  {
    // The signature is `Closure|object`. Any object key works as a WeakMap
    // key, so attaching a flag to a stdClass-shaped handler-bag should also
    // round-trip cleanly. Covers attribute-style class handlers down the line.
    $target = new stdClass();
    $flag = new Flag('marker');

    FlagDecorator::attach($target, $flag);

    self::assertSame([$flag], FlagDecorator::attached($target));
  }

  public function testAttachedReturnsEmptyArrayWhenNothingAttached(): void
  {
    // Reading attached flags on an unknown target must not throw or return a
    // surprising shape — it has to be the empty list so downstream consumers
    // can foreach without checking.
    $closure = static fn(): bool => true;

    self::assertSame([], FlagDecorator::attached($closure));
  }

  public function testResetClearsAllStoredAttachments(): void
  {
    // Test isolation hook — verify the reset() seam actually works so
    // setUp() above isn't a lie.
    $closure = static fn(): bool => true;
    FlagDecorator::attach($closure, new Flag('x'));

    self::assertCount(1, FlagDecorator::attached($closure));

    FlagDecorator::reset();

    self::assertSame([], FlagDecorator::attached($closure));
  }
}
