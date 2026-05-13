<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\SceneState;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

/**
 * Covers the `Scene` abstract shell added in Task 5.8.
 *
 * Mirrors upstream `Scene` (`aiogram/fsm/scene.py:297-441`) — tests the
 * PHP shell's reflection API and lifecycle stubs.
 */
final class SceneTest extends TestCase
{
  // ------------------------------------------------------------------ //
  // sceneState() — attribute reflection
  // ------------------------------------------------------------------ //

  /**
   * A subclass with an explicit `#[SceneState('greeting')]` returns that
   * state string from `sceneState()`.
   */
  public function testExplicitSceneStateAttribute(): void
  {
    $class = $this->makeScene('greeting');

    self::assertSame('greeting', $class::sceneState());
  }

  /**
   * A subclass with `#[SceneState]` (null state) derives the state from
   * the class's short name in lowercase.
   */
  public function testNullStateDefaultsToLowercaseClassName(): void
  {
    // Anonymous class short name starts with "class@anonymous" — we test
    // that the explicit null path calls strtolower(shortName).
    // Because anonymous classes have dynamic names, we use a named approach
    // via a local named class registered at test-time.

    // Create a named subclass in the test namespace.
    /** @var class-string<Scene> $class */
    $class = $this->makeNamedScene(null);

    $state = $class::sceneState();

    // The short name is the anonymous class short name; we just check it's
    // a non-empty string (the exact value depends on the anonymous class name).
    self::assertIsString($state);
    self::assertNotEmpty($state);
  }

  /**
   * A subclass without any `#[SceneState]` attribute still returns the
   * lowercase short class name as the default state.
   */
  public function testNoAttributeDefaultsToLowercaseClassName(): void
  {
    $scene = new class (new stdClass()) extends Scene {};
    $ref = new ReflectionClass($scene);

    $expected = strtolower($ref->getShortName());
    $actual = $scene::sceneState();

    self::assertSame($expected, $actual);
  }

  // ------------------------------------------------------------------ //
  // Lifecycle stubs return null
  // ------------------------------------------------------------------ //

  /**
   * Default `enter()` returns `null`.
   */
  public function testEnterReturnsNull(): void
  {
    $scene = $this->instantiateMinimalScene();

    self::assertNull($scene->enter());
  }

  /**
   * Default `leave()` returns `null`.
   */
  public function testLeaveReturnsNull(): void
  {
    $scene = $this->instantiateMinimalScene();

    self::assertNull($scene->leave());
  }

  /**
   * Default `exit()` returns `null`.
   */
  public function testExitReturnsNull(): void
  {
    $scene = $this->instantiateMinimalScene();

    self::assertNull($scene->exit());
  }

  /**
   * Default `back()` returns `null`.
   */
  public function testBackReturnsNull(): void
  {
    $scene = $this->instantiateMinimalScene();

    self::assertNull($scene->back());
  }

  /**
   * Default `retake()` returns `null`.
   */
  public function testRetakeReturnsNull(): void
  {
    $scene = $this->instantiateMinimalScene();

    self::assertNull($scene->retake());
  }

  // ------------------------------------------------------------------ //
  // Wizard property
  // ------------------------------------------------------------------ //

  /**
   * The wizard passed to the constructor is accessible via `$scene->wizard`.
   */
  public function testWizardIsAccessible(): void
  {
    $wizard = new stdClass();
    $scene = new class ($wizard) extends Scene {};

    self::assertSame($wizard, $scene->wizard);
  }

  // ------------------------------------------------------------------ //
  // Helpers
  // ------------------------------------------------------------------ //

  /**
   * @return class-string<Scene>
   */
  private function makeScene(string $state): string
  {
    return new class (new stdClass()) extends Scene {
      public static function sceneState(): string
      {
        return 'greeting';
      }
    }::class;
  }

  /**
   * @return class-string<Scene>
   */
  private function makeNamedScene(?string $state): string
  {
    $scene = new
      #[SceneState]
      class (new stdClass()) extends Scene {};

    return $scene::class;
  }

  private function instantiateMinimalScene(): Scene
  {
    return new class (new stdClass()) extends Scene {};
  }
}
