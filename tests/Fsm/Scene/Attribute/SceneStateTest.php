<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Scene\Attribute;

use Attribute;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\SceneState;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Covers the `#[SceneState]` attribute construction and reflection behaviour.
 */
final class SceneStateTest extends TestCase
{
  /**
   * The attribute stores the explicit state string unchanged.
   */
  public function testExplicitStateIsStored(): void
  {
    $attr = new SceneState('greeting');

    self::assertSame('greeting', $attr->state);
  }

  /**
   * Constructing with no argument (or null) stores `null`.
   */
  public function testDefaultStateIsNull(): void
  {
    $attr = new SceneState();

    self::assertNull($attr->state);
  }

  /**
   * Constructing with explicit `null` also stores `null`.
   */
  public function testExplicitNullIsNull(): void
  {
    $attr = new SceneState(null);

    self::assertNull($attr->state);
  }

  /**
   * `SceneState` targets classes only (`Attribute::TARGET_CLASS`).
   */
  public function testAttributeTargetsClass(): void
  {
    $ref = new ReflectionClass(SceneState::class);
    $attrs = $ref->getAttributes(Attribute::class);

    self::assertCount(1, $attrs);

    /** @var Attribute $inst */
    $inst = $attrs[0]->newInstance();

    self::assertSame(Attribute::TARGET_CLASS, $inst->flags);
  }

  /**
   * `SceneState` can be read from a concrete class via reflection without
   * instantiating the annotated class.
   */
  public function testReadableViaReflectionWithoutInstantiation(): void
  {
    $target = new class {}; // anonymous class — no SceneState applied

    // Apply annotation to a named fixture class via an inline declaration.
    // We test that getAttributes works on a class decorated at compile time.
    $fixture = new
      #[SceneState('wizard')]
      class {};

    $ref = new ReflectionClass($fixture);
    $attrs = $ref->getAttributes(SceneState::class);

    self::assertCount(1, $attrs);

    /** @var SceneState $inst */
    $inst = $attrs[0]->newInstance();

    self::assertSame('wizard', $inst->state);

    // Suppress unused-variable warning.
    unset($target);
  }

  /**
   * A class without the attribute returns an empty attributes array.
   */
  public function testClassWithoutAttributeHasNoSceneStateAttr(): void
  {
    $plain = new class {};
    $ref = new ReflectionClass($plain);

    self::assertSame([], $ref->getAttributes(SceneState::class));
  }
}
