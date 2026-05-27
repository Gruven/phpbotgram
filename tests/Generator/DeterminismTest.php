<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * End-to-end determinism check that exercises the actual `bin/generate.php`
 * CLI as a subprocess. Complementary to `PipelineTest::testRunIsDeterministicAcrossTwoInvocations`,
 * which calls the `Pipeline` class in-process — this test catches drift in
 * the CLI wrapper itself (argv parsing, default path resolution, exit code
 * propagation) that would slip past the in-process variant.
 *
 * Two CLI runs into separate tmp dirs are compared via per-file SHA-256
 * hashes folded into a single tree hash. The whole pipeline + cs-fixer pass
 * runs twice, so the test is intentionally slow (~10–15s on developer
 * laptops). Marked with `#[Group('slow')]` so contributors can exclude it
 * during quick local feedback loops via `phpunit --exclude-group slow`;
 * CI runs every group.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('slow')]
final class DeterminismTest extends TestCase
{
  public function testCliRunsAreDeterministic(): void
  {
    $tmpA = sys_get_temp_dir() . '/phpbg-det-a-' . bin2hex(random_bytes(8));
    $tmpB = sys_get_temp_dir() . '/phpbg-det-b-' . bin2hex(random_bytes(8));
    mkdir($tmpA, 0o755, true);
    mkdir($tmpB, 0o755, true);

    try {
      $repoRoot = \dirname(__DIR__, 2);
      $cli = $repoRoot . '/tools/generator/bin/generate.php';

      $cmdA = sprintf('php %s --out=%s 2>&1', escapeshellarg($cli), escapeshellarg($tmpA));
      $cmdB = sprintf('php %s --out=%s 2>&1', escapeshellarg($cli), escapeshellarg($tmpB));

      /** @var list<string> $outA */
      $outA = [];

      /** @var list<string> $outB */
      $outB = [];
      $exitA = 0;
      $exitB = 0;

      exec($cmdA, $outA, $exitA);
      exec($cmdB, $outB, $exitB);

      self::assertSame(0, $exitA, "first CLI run failed:\n" . implode("\n", $outA));
      self::assertSame(0, $exitB, "second CLI run failed:\n" . implode("\n", $outB));

      self::assertSame(
        $this->hashTree($tmpA),
        $this->hashTree($tmpB),
        'CLI runs must be byte-identical',
      );
    } finally {
      $this->rrmdir($tmpA);
      $this->rrmdir($tmpB);
    }
  }

  /**
   * Walks `$path` and folds every file's SHA-256 (keyed on its path relative
   * to `$path`) into a single tree-level hash. The relative-path key strips
   * `$path` so the two trees compared in the test have identical key sets
   * despite living at different absolute locations.
   */
  private function hashTree(string $path): string
  {
    /** @var array<string, string> $files */
    $files = [];

    $iter = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iter as $file) {
      if (!$file instanceof SplFileInfo) {
        continue;
      }

      if (!$file->isFile()) {
        continue;
      }

      $rel = ltrim(substr($file->getPathname(), \strlen($path)), '/');
      $sha = hash_file('sha256', $file->getPathname());

      if ($sha === false) {
        self::fail("Failed to hash {$file}");
      }

      $files[$rel] = $sha;
    }

    ksort($files);

    return hash('sha256', (string)json_encode($files, \JSON_THROW_ON_ERROR));
  }

  private function rrmdir(string $path): void
  {
    if (!is_dir($path)) {
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
