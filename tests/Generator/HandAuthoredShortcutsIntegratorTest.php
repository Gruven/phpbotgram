<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use Gruven\PhpBotGram\Generator\HandAuthoredShortcutPlan;
use Gruven\PhpBotGram\Generator\HandAuthoredShortcutsIntegrator;
use Gruven\PhpBotGram\Generator\ShortcutPlan;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\HandAuthoredShortcutPlan
 * @covers \Gruven\PhpBotGram\Generator\HandAuthoredShortcutsIntegrator
 */
final class HandAuthoredShortcutsIntegratorTest extends TestCase
{
  /** @var list<string> */
  private array $tempDirs = [];

  protected function tearDown(): void
  {
    foreach ($this->tempDirs as $dir) {
      $this->removeDirectory($dir);
    }

    $this->tempDirs = [];
  }

  public function testReturnsEmptyPlansWhenDirectoryDoesNotExist(): void
  {
    $integrator = new HandAuthoredShortcutsIntegrator(
      '/definitely/does/not/exist/' . bin2hex(random_bytes(8)),
      [],
    );

    self::assertSame([], $integrator->plans());
  }

  public function testReturnsEmptyPlansWhenDirectoryIsEmpty(): void
  {
    $dir = $this->makeTempDir();

    $integrator = new HandAuthoredShortcutsIntegrator($dir, []);

    self::assertSame([], $integrator->plans());
  }

  public function testDetectsSingleTraitWithDeclaredPublicMethods(): void
  {
    $dir = $this->makeTempDir();
    $namespace = $this->uniqueNamespace();

    $this->writeTraitFile(
      $dir,
      'MessageShortcuts.php',
      $namespace,
      'MessageShortcuts',
      <<<'PHP'
              public function isPm(): bool
              {
                return false;
              }

              public function getThreadId(): ?int
              {
                return null;
              }
      PHP,
    );

    $integrator = new HandAuthoredShortcutsIntegrator($dir, []);
    $plans = $integrator->plans();

    self::assertCount(1, $plans);

    $plan = $plans[0];
    self::assertInstanceOf(HandAuthoredShortcutPlan::class, $plan);
    self::assertSame('Message', $plan->ownerTypeName);
    self::assertSame($namespace . '\\MessageShortcuts', $plan->traitFqcn);
    self::assertSame('MessageShortcuts', $plan->traitShortName);
    self::assertSame(['isPm', 'getThreadId'], $plan->declaredMethods);
  }

  public function testFiltersOutNonPublicMethods(): void
  {
    $dir = $this->makeTempDir();
    $namespace = $this->uniqueNamespace();

    $this->writeTraitFile(
      $dir,
      'MessageShortcuts.php',
      $namespace,
      'MessageShortcuts',
      <<<'PHP'
              public function isPm(): bool
              {
                return false;
              }

              private function helper(): void
              {
              }

              protected function inner(): void
              {
              }
      PHP,
    );

    $integrator = new HandAuthoredShortcutsIntegrator($dir, []);
    $plans = $integrator->plans();

    self::assertCount(1, $plans);
    self::assertSame(['isPm'], $plans[0]->declaredMethods);
  }

  public function testSkipsNonTraitClassesInDirectory(): void
  {
    $dir = $this->makeTempDir();
    $namespace = $this->uniqueNamespace();

    $this->writeTraitFile(
      $dir,
      'MessageShortcuts.php',
      $namespace,
      'MessageShortcuts',
      <<<'PHP'
              public function isPm(): bool
              {
                return false;
              }
      PHP,
    );

    // Drop a *Shortcuts.php-suffixed file containing a CLASS (not a trait) —
    // the integrator must skip it without throwing.
    $classNamespace = $this->uniqueNamespace();

    file_put_contents(
      $dir . '/NotATraitShortcuts.php',
      "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$classNamespace};\n\nfinal class NotATraitShortcuts\n{\n  public function whatever(): void {}\n}\n",
    );

    $integrator = new HandAuthoredShortcutsIntegrator($dir, []);
    $plans = $integrator->plans();

    self::assertCount(1, $plans);
    self::assertSame('Message', $plans[0]->ownerTypeName);
  }

  public function testSkipsFilesWithoutShortcutsSuffix(): void
  {
    $dir = $this->makeTempDir();
    $namespace = $this->uniqueNamespace();

    $this->writeTraitFile(
      $dir,
      'MessageShortcuts.php',
      $namespace,
      'MessageShortcuts',
      <<<'PHP'
              public function isPm(): bool
              {
                return false;
              }
      PHP,
    );

    // Drop a file that doesn't match the *Shortcuts.php suffix. The integrator
    // must not even include() it (we never declared a class/trait, so a stray
    // include would either succeed silently or fail; we want neither effect).
    file_put_contents(
      $dir . '/IrrelevantHelper.php',
      "<?php\n\ndeclare(strict_types=1);\n\n// intentionally not a trait or class\n",
    );

    $integrator = new HandAuthoredShortcutsIntegrator($dir, []);
    $plans = $integrator->plans();

    self::assertCount(1, $plans);
    self::assertSame('MessageShortcuts', $plans[0]->traitShortName);
  }

  public function testThrowsOnCollisionWithAliasShortcut(): void
  {
    $dir = $this->makeTempDir();
    $namespace = $this->uniqueNamespace();

    $this->writeTraitFile(
      $dir,
      'MessageShortcuts.php',
      $namespace,
      'MessageShortcuts',
      <<<'PHP'
              public function answer(string $text): void
              {
              }
      PHP,
    );

    $aliasPlan = new ShortcutPlan(
      ownerTypeName: 'Message',
      aliasName: 'answer',
      phpMethodName: 'answer',
      methodEntityName: 'sendMessage',
      fill: ['chat_id' => 'self.chat.id'],
      ignore: [],
    );

    $integrator = new HandAuthoredShortcutsIntegrator($dir, [$aliasPlan]);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Message');
    $this->expectExceptionMessage('answer');
    $this->expectExceptionMessage('MessageShortcuts');

    $integrator->plans();
  }

  public function testDoesNotCollideWhenOwnerTypeDiffers(): void
  {
    $dir = $this->makeTempDir();
    $namespace = $this->uniqueNamespace();

    $this->writeTraitFile(
      $dir,
      'MessageShortcuts.php',
      $namespace,
      'MessageShortcuts',
      <<<'PHP'
              public function answer(string $text): void
              {
              }
      PHP,
    );

    // Alias is on Chat.answer — same method name but DIFFERENT owner type.
    // The trait file is owned by Message, so this must NOT throw.
    $aliasPlan = new ShortcutPlan(
      ownerTypeName: 'Chat',
      aliasName: 'answer',
      phpMethodName: 'answer',
      methodEntityName: 'sendMessage',
      fill: ['chat_id' => 'self.id'],
      ignore: [],
    );

    $integrator = new HandAuthoredShortcutsIntegrator($dir, [$aliasPlan]);
    $plans = $integrator->plans();

    self::assertCount(1, $plans);
    self::assertSame('Message', $plans[0]->ownerTypeName);
    self::assertSame(['answer'], $plans[0]->declaredMethods);
  }

  public function testRealRepoShortcutsDirectoryIsAbsent(): void
  {
    // Phase 1 ships no `src/Types/Shortcuts/` directory. The integrator must
    // accept that as a no-op without erroring — this is the integration smoke
    // test that proves the real-repo wiring point works.
    $shortcutsDir = dirname(__DIR__, 2) . '/src/Types/Shortcuts';

    self::assertDirectoryDoesNotExist($shortcutsDir);

    $integrator = new HandAuthoredShortcutsIntegrator($shortcutsDir, []);

    self::assertSame([], $integrator->plans());
  }

  private function makeTempDir(): string
  {
    $dir = sys_get_temp_dir() . '/phpbg-shortcuts-test-' . bin2hex(random_bytes(8));

    if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
      self::fail("Failed to create temp dir {$dir}");
    }

    $this->tempDirs[] = $dir;

    return $dir;
  }

  /**
   * Pick a unique namespace per trait declaration so PHP doesn't trip on a
   * double-declared trait when multiple tests run in the same process.
   */
  private function uniqueNamespace(): string
  {
    return 'PhpBotGram\\Tests\\Tmp\\Shortcuts_' . bin2hex(random_bytes(8));
  }

  private function writeTraitFile(
    string $dir,
    string $filename,
    string $namespace,
    string $traitName,
    string $body,
  ): void {
    $source = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\ntrait {$traitName}\n{\n{$body}\n}\n";
    file_put_contents($dir . '/' . $filename, $source);
  }

  private function removeDirectory(string $dir): void
  {
    if (!is_dir($dir)) {
      return;
    }

    foreach (scandir($dir) ?: [] as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }

      $path = $dir . '/' . $entry;

      if (is_dir($path)) {
        $this->removeDirectory($path);
      } else {
        unlink($path);
      }
    }

    rmdir($dir);
  }
}
