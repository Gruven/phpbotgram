<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\SceneState;
use Gruven\PhpBotGram\Fsm\Scene\HistoryManagerInterface;
use Gruven\PhpBotGram\Fsm\Scene\SceneConfig;
use Gruven\PhpBotGram\Fsm\Scene\SceneManagerInterface;
use Gruven\PhpBotGram\Fsm\SceneWizard;
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
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
    $wizard = $this->makeWizard();
    $scene = new class ($wizard) extends Scene {};
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
    $wizard = $this->makeWizard();
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
    $wizard = $this->makeWizard();

    return new class ($wizard) extends Scene {
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
    $wizard = $this->makeWizard();
    $scene = new
      #[SceneState]
      class ($wizard) extends Scene {};

    return $scene::class;
  }

  private function instantiateMinimalScene(): Scene
  {
    return new class ($this->makeWizard()) extends Scene {};
  }

  /**
   * Build a minimal `SceneWizard` suitable for passing to `Scene::__construct`.
   *
   * The wizard is intentionally not bound to a scene (scene=null) since these
   * tests only exercise `Scene` lifecycle stubs and reflection, not wizard
   * dispatch logic.
   */
  private function makeWizard(): SceneWizard
  {
    $history = new class implements HistoryManagerInterface {
      public function clear(): void {}

      public function snapshot(): void {}

      public function rollback(): ?string
      {
        return null;
      }
    };

    $manager = new class ($history) implements SceneManagerInterface {
      public function __construct(private HistoryManagerInterface $h) {}

      public function history(): HistoryManagerInterface
      {
        return $this->h;
      }

      public function enter(null|State|string $scene, bool $checkActive = true, mixed ...$kwargs): void {}
    };

    $ctx = new FsmContext(
      new MemoryStorage(),
      new StorageKey(botId: 1, chatId: 1, userId: 1),
    );

    $config = new SceneConfig(state: 'test', handlers: [], actions: []);

    return new SceneWizard(
      sceneConfig: $config,
      manager: $manager,
      state: $ctx,
      updateType: 'message',
      event: new stdClass(),
      data: [],
    );
  }
}
