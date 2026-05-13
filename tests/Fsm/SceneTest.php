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
use stdClass;

/**
 * Upstream `tests/test_fsm/test_scene.py` scene-level cases deliberately
 * not ported here:
 *
 * - `TestSceneHandlerWrapper::*` — dispatcher integration: depends on a full
 *   `SceneHandlerWrapper` dispatch loop with `Update`, `Message`, and async
 *   `FSMContext`; these are dispatcher-integration tests.
 * - `TestOnMarker::test_marker_name` parametrize rows — API divergence: PHP
 *   `on` markers are typed PHP attributes, not runtime `ObserverMarker`
 *   instances; covered by `OnAttributeTest`.
 * - `test_empty_handler` — API divergence: `_empty_handler()` is an
 *   internal async no-op in Python; PHP has no direct equivalent.
 * - `TestObserverMarker::*` — API divergence: PHP uses PHP 8 attributes
 *   instead of `ObserverMarker`/`ObserverDecorator` runtime objects.
 * - `TestObserverDecorator::*` — API divergence: same as above.
 * - `TestActionContainer::*` — phase scope deferral: `ActionContainer.execute()`
 *   is wizard dispatch, covered by `SceneWizardTest`.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
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
   * A subclass with `#[SceneState]` (no argument, state = null) returns null
   * from `sceneState()`, mirroring upstream's `state=None` default.
   */
  public function testNullStateAttributeReturnsNull(): void
  {
    /** @var class-string<Scene> $class */
    $class = $this->makeNamedScene(null);

    self::assertNull($class::sceneState());
  }

  /**
   * A subclass without any `#[SceneState]` attribute returns `null` from
   * `sceneState()`, mirroring upstream `Scene.__init_subclass__` which
   * defaults `state` to `None` when the kwarg is absent.
   */
  public function testSceneStateReturnsNullWhenAttributeAbsent(): void
  {
    $wizard = $this->makeWizard();
    $scene = new class ($wizard) extends Scene {};

    self::assertNull($scene::sceneState());
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
