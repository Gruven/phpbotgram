<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use FilesystemIterator;
use Gruven\PhpBotGram\Generator\FileEmitter;
use Gruven\PhpBotGram\Generator\Pipeline;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\Pipeline
 */
final class PipelineTest extends TestCase
{
  private static string $repoRoot = '';
  private static string $schemaDir = '';
  private string $tmpOut = '';

  public static function setUpBeforeClass(): void
  {
    self::$repoRoot = dirname(__DIR__, 2);
    self::$schemaDir = self::$repoRoot . '/.butcher';
  }

  protected function setUp(): void
  {
    $this->tmpOut = sys_get_temp_dir() . '/phpbg-gen-test-' . bin2hex(random_bytes(8));
    mkdir($this->tmpOut, 0o755, true);
  }

  protected function tearDown(): void
  {
    if (is_dir($this->tmpOut)) {
      $this->removeRecursively($this->tmpOut);
    }
  }

  public function testRunReturnsManifestWithWrittenAndSkippedKeys(): void
  {
    $manifest = $this->buildPipeline()->run();

    self::assertArrayHasKey('written', $manifest);
    self::assertArrayHasKey('skipped', $manifest);
    self::assertNotSame([], $manifest['written']);
    // The schema declares `InputFile` as a wire type; the renderer emits
    // `Types/InputFile.php` and the emitter always skips it (protected).
    // The remaining protected paths (Custom/DateTime, Unspecified, etc.)
    // have no schema entity and so don't appear in skipped unless
    // pre-populated — that case is exercised in testProtectedHandAuthoredFilesAreLeftUntouchedAndRecordedAsSkipped.
    self::assertContains('Types/InputFile.php', $manifest['skipped']);
  }

  public function testFullPipelineEmissionCountsMeetSchemaExpectations(): void
  {
    $this->buildPipeline()->run();

    $typeFiles = $this->phpFilesIn($this->tmpOut . '/Types');
    $methodFiles = $this->phpFilesIn($this->tmpOut . '/Methods');
    $enumFiles = $this->phpFilesIn($this->tmpOut . '/Enums');

    // Types include both schema types AND <Parent>Union resolvers — combined
    // the count exceeds 300 (305 schema types + 21 unions in the vendored
    // 10.0 schema). Pinning the lower bound at 300 leaves headroom for
    // schema patches that add/remove a handful of types without breaking
    // the test on every minor refresh.
    self::assertGreaterThanOrEqual(300, count($typeFiles));
    self::assertCount(176, $methodFiles);
    self::assertCount(34, $enumFiles);
    self::assertFileExists($this->tmpOut . '/Bot.php');
  }

  public function testRunIsDeterministicAcrossTwoInvocations(): void
  {
    $first = $this->snapshotTree($this->runIntoFreshDir());
    $second = $this->snapshotTree($this->runIntoFreshDir());

    self::assertSame(array_keys($first), array_keys($second), 'File set differs across runs');

    foreach ($first as $rel => $hash) {
      self::assertSame($hash, $second[$rel], "Content differs for {$rel}");
    }
  }

  public function testProtectedHandAuthoredFilesAreLeftUntouchedAndRecordedAsSkipped(): void
  {
    // Use cs-fixer-clean sentinel content so the final cs-fixer pass doesn't
    // reformat the pre-populated files — that would mask the real assertion
    // ("the emitter never overwrites a protected path"). In production these
    // hand-authored files are already cs-fixer-clean, so the pass is a no-op
    // against them; the test mirrors that invariant via canned content.
    $sentinelFor = static fn(string $rel): string => "<?php\n\n// sentinel for {$rel}\n";

    foreach (FileEmitter::PROTECTED_PATHS as $rel) {
      $abs = $this->tmpOut . '/' . $rel;
      @mkdir(\dirname($abs), 0o755, true);
      file_put_contents($abs, $sentinelFor($rel));
    }

    $manifest = $this->buildPipeline()->run();

    foreach (FileEmitter::PROTECTED_PATHS as $rel) {
      $abs = $this->tmpOut . '/' . $rel;

      self::assertFileExists($abs);
      self::assertSame(
        $sentinelFor($rel),
        file_get_contents($abs),
        "Protected file {$rel} was overwritten",
      );
    }

    // Only protected paths whose names also correspond to schema entities
    // appear in `skipped` — the others (e.g. `Custom/DateTime.php`,
    // `MutableTelegramObject.php`) have no matching schema entity, so the
    // pipeline never tries to emit them and they don't get recorded. The
    // important guarantee — pre-populated content survives untouched — is
    // checked above.
    self::assertContains('Types/InputFile.php', $manifest['skipped']);

    // Bot.php is not protected — should be in `written`.
    self::assertContains('Bot.php', $manifest['written']);
  }

  public function testEveryEmittedPhpFilePassesPhpLint(): void
  {
    $this->buildPipeline()->run();

    foreach ($this->phpFilesIn($this->tmpOut) as $file) {
      $out = [];
      $exit = 0;
      exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $exit);
      self::assertSame(0, $exit, "php -l failed for {$file}:\n" . implode("\n", $out));
    }
  }

  public function testCsFixerHasNoDiffAgainstEmittedTree(): void
  {
    $this->buildPipeline()->run();

    $cmd = sprintf(
      '%s fix --dry-run --diff %s 2>&1',
      escapeshellarg(self::$repoRoot . '/vendor/bin/php-cs-fixer'),
      escapeshellarg($this->tmpOut),
    );

    $previousCwd = getcwd();

    if (!chdir(self::$repoRoot)) {
      self::fail('Failed to chdir to repo root: ' . self::$repoRoot);
    }

    try {
      $output = [];
      $exitCode = 0;
      exec($cmd, $output, $exitCode);
      self::assertSame(0, $exitCode, "cs-fixer found diffs in generated output:\n" . implode("\n", $output));
    } finally {
      if ($previousCwd !== false) {
        chdir($previousCwd);
      }
    }
  }

  private function buildPipeline(): Pipeline
  {
    return new Pipeline(
      schemaDir: self::$schemaDir,
      outDir: $this->tmpOut,
      fixerBin: self::$repoRoot . '/vendor/bin/php-cs-fixer',
      repoRoot: self::$repoRoot,
    );
  }

  private function runIntoFreshDir(): string
  {
    $dir = sys_get_temp_dir() . '/phpbg-det-' . bin2hex(random_bytes(8));
    mkdir($dir, 0o755, true);

    new Pipeline(
      schemaDir: self::$schemaDir,
      outDir: $dir,
      fixerBin: self::$repoRoot . '/vendor/bin/php-cs-fixer',
      repoRoot: self::$repoRoot,
    )->run();

    return $dir;
  }

  /**
   * @return array<string, string>
   */
  private function snapshotTree(string $root): array
  {
    /** @var array<string, string> $out */
    $out = [];

    foreach ($this->phpFilesIn($root) as $path) {
      $rel = substr($path, \strlen($root) + 1);
      $sha = sha1_file($path);

      if ($sha === false) {
        self::fail("Failed to hash {$path}");
      }

      $out[$rel] = $sha;
    }

    ksort($out);

    // Cleanup after snapshotting so the dir doesn't leak between test cases.
    $this->removeRecursively($root);

    return $out;
  }

  /**
   * @return list<string>
   */
  private function phpFilesIn(string $root): array
  {
    if (!is_dir($root)) {
      return [];
    }

    /** @var list<string> $out */
    $out = [];

    $iter = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iter as $entry) {
      if (!$entry instanceof SplFileInfo) {
        continue;
      }

      if ($entry->isFile() && str_ends_with($entry->getFilename(), '.php')) {
        $out[] = $entry->getPathname();
      }
    }

    sort($out, SORT_STRING);

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
