<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use FilesystemIterator;
use Gruven\PhpBotGram\Generator\FileEmitter;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\FileEmitter
 */
final class FileEmitterTest extends TestCase
{
  private string $tmp = '';

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/phpbg-emit-' . bin2hex(random_bytes(8));
    mkdir($this->tmp, 0o755, true);
  }

  protected function tearDown(): void
  {
    if (is_dir($this->tmp)) {
      $this->removeRecursively($this->tmp);
    }
  }

  public function testWritesFileUnderOutDir(): void
  {
    $emitter = new FileEmitter($this->tmp);

    $outcome = $emitter->emit('Types/Foo.php', "<?php\n// foo\n");

    self::assertSame('written', $outcome);
    self::assertFileExists($this->tmp . '/Types/Foo.php');
    self::assertSame("<?php\n// foo\n", file_get_contents($this->tmp . '/Types/Foo.php'));
  }

  public function testSkipsProtectedPathAndLeavesSentinelContentUntouched(): void
  {
    $emitter = new FileEmitter($this->tmp);

    mkdir($this->tmp . '/Types', 0o755, true);
    $sentinel = "<?php\n// hand-authored sentinel\n";
    file_put_contents($this->tmp . '/Types/InputFile.php', $sentinel);

    $outcome = $emitter->emit('Types/InputFile.php', "<?php\n// generated\n");

    self::assertSame('skipped', $outcome);
    self::assertSame($sentinel, file_get_contents($this->tmp . '/Types/InputFile.php'));
  }

  public function testEveryProtectedPathRoundTripsToSkipped(): void
  {
    $emitter = new FileEmitter($this->tmp);

    foreach (FileEmitter::PROTECTED_PATHS as $protected) {
      self::assertSame('skipped', $emitter->emit($protected, '<?php // ignored'));
    }
  }

  public function testCreatesNestedParentDirectories(): void
  {
    $emitter = new FileEmitter($this->tmp);

    $outcome = $emitter->emit('Methods/Sub/Foo.php', "<?php\n// nested\n");

    self::assertSame('written', $outcome);
    self::assertDirectoryExists($this->tmp . '/Methods/Sub');
    self::assertFileExists($this->tmp . '/Methods/Sub/Foo.php');
  }

  public function testNoTempFilesSurviveAfterSuccessfulEmit(): void
  {
    $emitter = new FileEmitter($this->tmp);

    $emitter->emit('Types/Foo.php', "<?php\n// foo\n");
    $emitter->emit('Methods/Bar.php', "<?php\n// bar\n");
    $emitter->emit('Methods/Sub/Baz.php', "<?php\n// baz\n");

    $survivors = $this->findFiles($this->tmp, '/\\.tmp\\./');

    self::assertSame([], $survivors, 'No *.tmp.* files should survive a successful emit');
  }

  public function testOverwritesExistingNonProtectedFile(): void
  {
    $emitter = new FileEmitter($this->tmp);

    mkdir($this->tmp . '/Types', 0o755, true);
    file_put_contents($this->tmp . '/Types/Foo.php', '<?php // old');

    $outcome = $emitter->emit('Types/Foo.php', '<?php // new');

    self::assertSame('written', $outcome);
    self::assertSame('<?php // new', file_get_contents($this->tmp . '/Types/Foo.php'));
  }

  /**
   * @return list<string>
   */
  private function findFiles(string $root, string $regex): array
  {
    /** @var list<string> $out */
    $out = [];

    $iter = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iter as $entry) {
      if (!$entry instanceof SplFileInfo || !$entry->isFile()) {
        continue;
      }

      $name = $entry->getFilename();

      if (preg_match($regex, $name) === 1) {
        $out[] = $entry->getPathname();
      }
    }

    return $out;
  }

  private function removeRecursively(string $path): void
  {
    if (!is_dir($path)) {
      if (is_file($path)) {
        @unlink($path);
      }

      return;
    }

    $iter = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iter as $entry) {
      if (!$entry instanceof SplFileInfo) {
        continue;
      }

      if ($entry->isDir()) {
        @rmdir($entry->getPathname());
      } else {
        @unlink($entry->getPathname());
      }
    }

    @rmdir($path);
  }
}
